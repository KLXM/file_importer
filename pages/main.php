<?php
namespace Klxm\FileImporter;

use rex_i18n;

// Prüfe ob mindestens ein Provider konfiguriert ist
$provider = $this->providers['pixabay'] ?? null;
if (!$provider || !$provider->isConfigured()) {
    echo \rex_view::error(rex_i18n::msg('file_importer_no_provider_configured'));
    echo '<p><a href="'.rex_url::backendPage('file_importer/config').'" class="btn btn-primary">'.rex_i18n::msg('file_importer_goto_config').'</a></p>';
    return;
}

// Medienpool Kategorien laden
$cats_sel = new \rex_media_category_select();
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
                    <form id="file-importer-search" class="file-importer-search">
                        <div class="input-group">
                            <input type="text" 
                                   class="form-control" 
                                   id="file-importer-query" 
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
    </div>
    
    <!-- Benachrichtigungen -->
    <div id="file-importer-alerts"></div>
    
    <!-- Status -->
    <div id="file-importer-status" class="file-importer-status"></div>
    
    <!-- Ergebnisse -->
    <div class="panel panel-default">
        <div class="panel-body">
            <div id="file-importer-results" class="file-importer-results"></div>
            
            <!-- Lade-Indikator -->
            <div id="file-importer-loadmore" class="file-importer-load-more">
                <i class="rex-icon fa-spinner fa-spin" style="display: none;"></i>
            </div>
        </div>
    </div>
    
    <!-- Attribution -->
    <div class="file-importer-attribution text-center">
        ' . rex_i18n::msg('file_importer_pixabay_attribution') . '
    </div>
</div>

<!-- Template für Ergebnis-Items -->
<script type="text/template" id="file-importer-template">
    <div class="file-importer-item">
        <div class="file-importer-preview">
            <img src="{preview_url}" alt="{title}">
        </div>
        <div class="file-importer-info">
            <div class="file-importer-title">{title}</div>
            <select class="file-importer-size-select form-control">
                <!-- Wird dynamisch befüllt -->
            </select>
            <div class="file-importer-actions">
                <button class="btn btn-default file-importer-preview-btn">
                    <i class="rex-icon fa-eye"></i>
                </button>
                <button class="btn btn-primary file-importer-import-btn">
                    <i class="rex-icon fa-download"></i>
                </button>
            </div>
            <div class="progress file-importer-progress">
                <div class="progress-bar progress-bar-striped active" role="progressbar" style="width: 100%">
                    ' . rex_i18n::msg('file_importer_importing') . '
                </div>
            </div>
        </div>
    </div>
</script>';

// Fragment erstellen und ausgeben
$fragment = new \rex_fragment();
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
