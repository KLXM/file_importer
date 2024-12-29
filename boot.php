<?php
namespace Klxm\FileImporter;

use Klxm\FileImporter\Provider\PixabayProvider;

// Nur im Backend ausführen
if (\rex::isBackend() && \rex::getUser()) {

    // Provider registrieren
    if (!isset($this->providers)) {
        $this->providers = [];
    }
    $this->providers['pixabay'] = new PixabayProvider();


        \rex_view::addCssFile($this->getAssetsUrl('file_importer.css'));

     
  

    // AJAX Handler für API Anfragen
    if (\rex_request('file_importer_api', 'bool', false)) {
        \rex_logger::factory()->log('debug', 'File Importer API Request', [
            'request' => $_REQUEST,
            'get' => $_GET,
            'post' => $_POST
        ]);

        try {
            $action = \rex_request('action', 'string');
            $provider = \rex_request('provider', 'string');
            $query = \rex_request('query', 'string', '');
            $page = \rex_request('page', 'integer', 1);
            
            // Debug-Ausgabe vor der Verarbeitung
            \rex_logger::factory()->log('debug', 'Processing API Request', [
                'action' => $action,
                'provider' => $provider,
                'query' => $query,
                'page' => $page
            ]);
            
            if (!isset($this->providers[$provider])) {
                throw new \rex_exception('Invalid provider');
            }

            $providerInstance = $this->providers[$provider];

            switch ($action) {
                case 'search':
                    $options = [
                        'type' => \rex_request('type', 'string', 'all'),
                        'category' => \rex_request('category', 'string', '')
                    ];
                    $results = $providerInstance->search($query, $page, $options);
                    \rex_response::sendJson(['success' => true, 'data' => $results]);
                    break;

                case 'download':
                    $url = \rex_request('url', 'string');
                    $filename = \rex_request('filename', 'string');
                    $result = $providerInstance->download($url, $filename);
                    \rex_response::sendJson(['success' => $result]);
                    break;

                default:
                    throw new \rex_exception('Invalid action');
            }

        } catch (\Exception $e) {
            \rex_logger::logException($e);
            \rex_response::sendJson([
                'success' => false, 
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }
}

// Cache leeren wenn Provider-Einstellungen gespeichert werden
\rex_extension::register('REX_FORM_SAVED', function($ep) {
    if (strpos(\rex_be_controller::getCurrentPage(), 'file_importer') === 0) {
        // Cache-Files löschen
        $cacheFolder = \rex_path::addonCache('file_importer');
        if (is_dir($cacheFolder)) {
            array_map('unlink', glob($cacheFolder . '/*.cache'));
        }
    }
});
