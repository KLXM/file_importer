<?php
namespace Klxm\FileImporter\Provider;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;

    public function __construct()
    {
        $this->loadConfig();
        dump('Config loaded:', $this->config);
    }

    protected function loadConfig(): void
    {
        $this->config = \rex_addon::get('file_importer')->getConfig('pixabay') ?? [];
        dump('Loading config from addon:', \rex_addon::get('file_importer')->getConfig());
    }

    public function getName(): string
    {
        return 'pixabay';
    }

    public function isConfigured(): bool
    {
        $configured = isset($this->config['apikey']) && !empty($this->config['apikey']);
        dump('is configured?', $configured, $this->config);
        return $configured;
    }

    public function getConfigFields(): array
    {
        return [
            [
                'label' => 'file_importer_pixabay_apikey',
                'name' => 'apikey',
                'type' => 'text',
                'notice' => 'file_importer_pixabay_apikey_notice'
            ]
        ];
    }

    public function search(string $query, int $page = 1, array $options = []): array
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Pixabay API key not configured');
        }

        $type = $options['type'] ?? 'image';
        
        // Debug-Log für Suchparameter
        dump('Search parameters:', [
            'query' => $query,
            'page' => $page,
            'type' => $type,
            'config' => $this->config
        ]);

        // Basis-URL basierend auf dem Typ
        $baseUrl = ($type === 'video') ? $this->apiUrlVideos : $this->apiUrl;
        
        // API Parameter
        $params = [
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => 'de'
        ];
        dump($params);

        if ($type === 'image') {
            $params['image_type'] = 'all';
        }

        $url = $baseUrl . '?' . http_build_query($params);
        
        // Debug URL (ohne API Key)
        $debugUrl = preg_replace('/key=([^&]+)/', 'key=XXXXX', $url);
        dump('API URL:', $debugUrl);

        try {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_TIMEOUT => 20
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if ($response === false) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            $data = json_decode($response, true);
            if (!isset($data['hits'])) {
                dump('API Error Response:', $data);
                throw new \Exception('Invalid response from Pixabay API');
            }

            $results = [
                'items' => array_map(function($item) use ($type) {
                    if ($type === 'video') {
                        return [
                            'id' => $item['id'],
                            'preview_url' => $item['picture_id'] ? "https://i.vimeocdn.com/video/{$item['picture_id']}_640x360.jpg" : '',
                            'title' => $item['tags'],
                            'author' => $item['user'],
                            'type' => 'video',
                            'size' => [
                                'tiny' => ['url' => $item['videos']['tiny']['url']],
                                'small' => ['url' => $item['videos']['small']['url']],
                                'medium' => ['url' => $item['videos']['medium']['url']],
                                'large' => ['url' => $item['videos']['large']['url'] ?? $item['videos']['medium']['url']]
                            ]
                        ];
                    } else {
                        return [
                            'id' => $item['id'],
                            'preview_url' => $item['webformatURL'],
                            'title' => $item['tags'],
                            'author' => $item['user'],
                            'type' => 'image',
                            'size' => [
                                'preview' => ['url' => $item['previewURL']],
                                'web' => ['url' => $item['webformatURL']],
                                'large' => ['url' => $item['largeImageURL']],
                                'original' => ['url' => $item['imageURL'] ?? $item['largeImageURL']]
                            ]
                        ];
                    }
                }, $data['hits']),
                'total' => $data['totalHits'],
                'page' => $page,
                'total_pages' => ceil($data['totalHits'] / $this->itemsPerPage)
            ];

            return $results;

        } catch (\Exception $e) {
            dump('API Error:', $e->getMessage());
            throw $e;
        }
    }

    public function download(string $url, string $filename): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Pixabay API key not configured');
        }

        $filename = $this->sanitizeFilename($filename);
        
        // Bestimme die Dateiendung basierend auf der URL
        $extension = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION);
        if (!$extension) {
            $extension = strpos($url, 'vimeocdn.com') !== false ? 'mp4' : 'jpg';
        }
        
        $filename = $filename . '.' . $extension;

        return $this->downloadFile($url, $filename);
    }
}
