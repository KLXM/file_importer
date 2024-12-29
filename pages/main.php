<?php
namespace Klxm\FileImporter;

use rex_i18n;
use rex_url;
use rex_view;
use rex_media_category_select;
use rex_fragment;

// Prüfe ob mindestens ein Provider konfiguriert ist
$provider = $this->providers['pixabay'] ?? null;
if (!$provider || !$provider->isConfigured()) {
    echo rex_view::error(rex_i18n::msg('file_importer_no_provider_configured'));
    echo '<p><a href="'.rex_url::backendPage('file_importer/config').'" class="btn btn-primary">'.rex_i18n::msg('file_importer_goto_config').'</a></p>';
    return;
}

// Suche durchführen wenn Query vorhanden
$searchResults = [];
$searchQuery = rex_get('query', 'string', '');
$page = max(1, rex_get('page', 'int', 1));

if ($searchQuery) {
    try {
        $searchResults = $provider->search($searchQuery, $page);
    } catch (\Exception $e) {
        echo rex_view::error($e->getMessage());
    }
}

// Download/Import durchführen
if (rex_post('import', 'bool') && rex_post('url', 'string') && rex_post('filename', 'string')) {
    try {
        $success = $provider->download(
            rex_post('url', 'string'),
            rex_post('filename', 'string')
        );
        
        if ($success) {
            echo rex_view::success(rex_i18n::msg('file_importer_import_success'));
        } else {
            echo rex_view::error(rex_i18n::msg('file_importer_import_error'));
        }
    } catch (\Exception $e) {
        echo rex_view::error($e->getMessage());
    }
}

// Medienpool Kategorien laden
$cats_sel = new rex_media_category_select();
$cats_sel->setStyle('class="form-control"');
$cats_sel->setName('category_id');
$cats_sel->setId('rex-mediapool-category');
$cats_sel->setSize(1);
$cats_sel->setAttribute('class', 'form-control');

// Hauptcontainer
$content = '
<div class="file-importer-container">
    <div class="row">
        <!-- Kategorie-Auswahl -->
        <div class="col-sm-4">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">' . rex_i18n::msg('file_importer_target_category') . '</div>
                </header>
                <div class="panel-body">
                    ' . $cats_sel->get() . '
                </div>
            </div>
        </div>
        
        <!-- Suchbereich -->
        <div class="col-sm-8">
            <div class="panel panel-default">
                <header class="panel-heading">
                    <div class="panel-title">
                        <i class="rex-icon fa-search"></i> ' . rex_i18n::msg('file_importer_search') . '
                    </div>
                </header>
                <div class="panel-body">
                    <form method="get" action="' . rex_url::currentBackendPage() . '">
                        <input type="hidden" name="page" value="file_importer/main">
                        <div class="input-group">
                            <select name="type" class="form-control" style="width: 100px; border-right: 0;">
                                <option value="image" ' . (rex_get('type', 'string', 'image') === 'image' ? 'selected' : '') . '>Bilder</option>
                                <option value="video" ' . (rex_get('type', 'string') === 'video' ? 'selected' : '') . '>Videos</option>
                            </select>
                            <input type="text" 
                                   class="form-control" 
                                   name="query" 
                                   value="' . rex_escape($searchQuery) . '"
                                   placeholder="' . rex_i18n::msg('file_importer_search_placeholder') . '"
                                   required>
                            <span class="input-group-btn">
                                <button class="btn btn-primary" type="submit">
                                    <i class="rex-icon fa-search"></i>
                                    ' . rex_i18n::msg('file_importer_search') . '
                                </button>
                            </span>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>';

// Suchergebnisse anzeigen
if ($searchResults && isset($searchResults['items'])) {
    $content .= '
    <div class="panel panel-default">
        <div class="panel-heading">
            <div class="panel-title">
                ' . count($searchResults['items']) . ' ' . rex_i18n::msg('file_importer_results_found') . '
            </div>
        </div>
        <div class="panel-body">
            <div class="file-importer-results">';
    
    foreach ($searchResults['items'] as $item) {
        $content .= '
            <div class="file-importer-item">
                <div class="file-importer-preview">
                    <img src="' . rex_escape($item['preview_url']) . '" alt="' . rex_escape($item['title']) . '">
                </div>
                <div class="file-importer-info">
                    <div class="file-importer-title">' . rex_escape($item['title']) . '</div>
                    <form action="' . rex_url::currentBackendPage() . '" method="post">
                        <input type="hidden" name="import" value="1">
                        <select name="url" class="form-control file-importer-size-select">';
        
        foreach ($item['size'] as $key => $value) {
            $content .= '<option value="' . rex_escape($value['url']) . '">' . rex_escape(ucfirst($key)) . '</option>';
        }
        
        $content .= '
                        </select>
                        <input type="hidden" name="filename" value="' . rex_escape($item['title']) . '">
                        <div class="file-importer-actions">
                            <button type="submit" class="btn btn-primary btn-block">
                                <i class="rex-icon fa-download"></i> ' . rex_i18n::msg('file_importer_import') . '
                            </button>
                        </div>
                    </form>
                </div>
            </div>';
    }
    
    $content .= '
            </div>
        </div>';
    
    // Pagination
    if ($searchResults['total_pages'] > 1) {
        $content .= '
        <div class="panel-footer">
            <nav aria-label="Page navigation">
                <ul class="pagination">';
        
        // Previous
        if ($page > 1) {
            $content .= '
                    <li>
                        <a href="' . rex_url::currentBackendPage(['query' => $searchQuery, 'page' => $page - 1]) . '">
                            &laquo;
                        </a>
                    </li>';
        }
        
        // Page Numbers
        for ($i = 1; $i <= $searchResults['total_pages']; $i++) {
            if ($i == $page) {
                $content .= '<li class="active"><span>' . $i . '</span></li>';
            } else {
                $content .= '
                    <li>
                        <a href="' . rex_url::currentBackendPage(['query' => $searchQuery, 'page' => $i]) . '">
                            ' . $i . '
                        </a>
                    </li>';
            }
        }
        
        // Next
        if ($page < $searchResults['total_pages']) {
            $content .= '
                    <li>
                        <a href="' . rex_url::currentBackendPage(['query' => $searchQuery, 'page' => $page + 1]) . '">
                            &raquo;
                        </a>
                    </li>';
        }
        
        $content .= '
                </ul>
            </nav>
        </div>';
    }
    
    $content .= '
    </div>';
}

$content .= '
    <!-- Attribution -->
    <div class="file-importer-attribution text-center">
        ' . rex_i18n::msg('file_importer_pixabay_attribution') . '
    </div>
</div>';

// Fragment erstellen und ausgeben
$fragment = new rex_fragment();
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
