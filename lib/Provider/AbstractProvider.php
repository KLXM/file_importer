<?php
namespace Klxm\FileImporter\Provider;

abstract class AbstractProvider implements ProviderInterface
{
    protected array $config = [];
    protected string $cachePrefix = 'fileimporter.search.';

    public function __construct()
    {
        $this->loadConfig();
    }

    protected function loadConfig(): void
    {
        $this->config = \rex_config::get('file_importer', $this->getName()) ?? [];
    }

    protected function saveConfig(array $config): void
    {
        \rex_config::set('file_importer', $this->getName(), $config);
    }

    protected function getCacheKey(string $query, int $page, array $options = []): string
    {
        return $this->cachePrefix . $this->getName() . '.' . md5($query . $page . serialize($options));
    }

    protected function getFromCache(string $key)
    {
        $cacheFile = \rex_path::addonCache('file_importer', $key . '.cache');
        if (!file_exists($cacheFile)) {
            return false;
        }

        $cache = unserialize(file_get_contents($cacheFile));
        if (!$cache || !isset($cache['expires']) || $cache['expires'] < time()) {
            @unlink($cacheFile);
            return false;
        }

        return $cache['data'];
    }

    protected function setCache(string $key, $data, int $expiration = 86400): void
    {
        $cacheFile = \rex_path::addonCache('file_importer', $key . '.cache');
        $cache = [
            'data' => $data,
            'expires' => time() + $expiration
        ];
        
        file_put_contents($cacheFile, serialize($cache));
    }

    protected function sanitizeFilename(string $filename): string
    {
        $filename = mb_convert_encoding($filename, 'UTF-8', 'auto');
        $filename = \rex_string::normalize($filename);
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        return trim($filename, '_');
    }

    protected function downloadFile(string $url, string $filename): bool
    {
        try {
            $tmpFile = \rex_path::cache('file_importer_' . uniqid() . '_' . $filename);
            
            $ch = curl_init($url);
            $fp = fopen($tmpFile, 'wb');
            
            curl_setopt_array($ch, [
                CURLOPT_FILE => $fp,
                CURLOPT_HEADER => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_TIMEOUT => 60
            ]);
            
            $success = curl_exec($ch);
            
            if (curl_errno($ch)) {
                throw new \Exception(curl_error($ch));
            }
            
            curl_close($ch);
            fclose($fp);
            
            if ($success) {
                $media = [
                    'title' => pathinfo($filename, PATHINFO_FILENAME),
                    'file' => [
                        'name' => $filename,
                        'path' => $tmpFile,
                        'tmp_name' => $tmpFile
                    ],
                    'category_id' => \rex_post('category_id', 'int', 0)
                ];
                
                $result = \rex_media_service::addMedia($media, true);
                unlink($tmpFile);
                
                return $result;
            }
            
            return false;
            
        } catch (\Exception $e) {
            \rex_logger::logException($e);
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
            return false;
        }
    }
}
