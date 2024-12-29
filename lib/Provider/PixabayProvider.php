<?php
namespace Klxm\FileImporter\Provider;

class PixabayProvider extends AbstractProvider
{
    protected string $apiUrl = 'https://pixabay.com/api/';
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

        $cacheKey = $this->getCacheKey($query, $page, $options);
        $cached = $this->getFromCache($cacheKey);

        if ($cached !== false) {
            return $cached;
        }

        $params = [
            'key' => $this->config['apikey'],
            'q' => urlencode($query),
            'page' => $page,
            'per_page' => $this->itemsPerPage,
            'image_type' => 'all',
            'lang' => \rex_i18n::getLanguage()
        ];

        $url = $this->apiUrl . '?' . http_build_query($params);

        try {
            $response = file_get_contents($url);
            if ($response === false) {
                throw new \Exception('Failed to fetch data from Pixabay API');
            }

            $data = json_decode($response, true);
            if (!isset($data['hits'])) {
                throw new \Exception('Invalid response from Pixabay API');
            }

            $results = [
                'items' => array_map(function($item) {
                    return [
                        'id' => $item['id'],
                        'preview_url' => $item['webformatURL'],
                        'download_url' => $item['largeImageURL'],
                        'title' => $item['tags'],
                        'author' => $item['user'],
                        'width' => $item['webformatWidth'],
                        'height' => $item['webformatHeight'],
                        'size' => [
                            'preview' => ['url' => $item['previewURL']],
                            'web' => ['url' => $item['webformatURL']],
                            'large' => ['url' => $item['largeImageURL']],
                            'original' => ['url' => $item['largeImageURL']]
                        ]
                    ];
                }, $data['hits']),
                'total' => $data['totalHits'],
                'page' => $page,
                'total_pages' => ceil($data['totalHits'] / $this->itemsPerPage)
            ];

            $this->setCache($cacheKey, $results);
            return $results;

        } catch (\Exception $e) {
            \rex_logger::logException($e);
            throw $e;
        }
    }

    public function download(string $url, string $filename): bool
    {
        if (!$this->isConfigured()) {
            throw new \Exception('Pixabay API key not configured');
        }

        $filename = $this->sanitizeFilename($filename);
        if (!pathinfo($filename, PATHINFO_EXTENSION)) {
            $filename .= '.jpg';
        }

        return $this->downloadFile($url, $filename);
    }
}
