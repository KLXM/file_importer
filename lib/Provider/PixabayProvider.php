<?php
namespace Klxm\FileImporter\Provider;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
    protected string $apiUrlVideos = 'https://pixabay.com/api/videos/';
    protected int $itemsPerPage = 20;

    public function getName(): string
    {
        return 'pixabay';
    }

    public function isConfigured(): bool
    {
        return isset($this->config['apikey']) && !empty($this->config['apikey']);
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
        $debug = "Pixabay Search Call:\n";
        $debug .= "Query: " . $query . "\n";
        $debug .= "Page: " . $page . "\n";
        $debug .= "Type: " . $type . "\n";
        $debug .= "API Key set: " . (!empty($this->config['apikey']) ? 'yes' : 'no') . "\n";
        
        \rex_logger::factory()->log('debug', $debug);

        // Basis-URL basierend auf dem Typ
        $baseUrl = ($type === 'video') ? $this->apiUrlVideos : $this->apiUrl;
        
        // API Parameter
        $params = [
            'key' => $this->config['apikey'],
            'q' => $query,
            'page' => $page,
            'per_page' => $this->itemsPerPage,
            'safesearch' => 'true',
            'lang' => 'de'  // Explizit auf Deutsch setzen
        ];

        if ($type === 'image') {
            $params['image_type'] = 'all';
        }

        $url = $baseUrl . '?' . http_build_query($params);
        
        // Debug URL (ohne API Key)
        $debugUrl = preg_replace('/key=([^&]+)/', 'key=XXXXX', $url);
        dump('API URL', $debugUrl);

        // Debug-Log für API-Aufruf
        \rex_logger::factory()->log('debug', 'Pixabay API Call', [
            'url' => preg_replace('/key=([^&]+)/', 'key=XXXXX', $url), // API-Key verstecken
            'full_params' => $params
        ]);

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

            $debug = "Pixabay API Response:\n";
            $debug .= "HTTP Code: " . $httpCode . "\n";
            $debug .= "Curl Error: " . curl_error($ch) . "\n";
            $debug .= "Response Preview: " . substr($response, 0, 1000) . "\n";
            
            \rex_logger::factory()->log('debug', $debug);

            if ($response === false) {
                throw new \Exception('Curl error: ' . curl_error($ch));
            }

            curl_close($ch);

            $data = json_decode($response, true);
            if (!isset($data['hits'])) {
                \rex_logger::factory()->log('error', "Invalid API Response:\n" . $response);
                throw new \Exception('Invalid response from Pixabay API');
            }
            
            // Debug der API-Antwort
            dump('Raw API Response', [
                'total' => $data['totalHits'],
                'sample_hits' => array_slice($data['hits'], 0, 3)
            ]);

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

            // Debug-Log für verarbeitete Ergebnisse
            \rex_logger::factory()->log('debug', 'Pixabay Search Results', [
                'total_hits' => $data['totalHits'],
                'items_count' => count($results['items']),
                'current_page' => $page,
                'total_pages' => $results['total_pages']
            ]);

            return $results;

        } catch (\Exception $e) {
            \rex_logger::factory()->log('error', 'Pixabay Search Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
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
