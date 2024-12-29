<?php
namespace Klxm\FileImporter;

$addon = \rex_addon::get('file_importer');

// Provider Status aktualisieren
if (\rex_post('provider-status-submit', 'boolean')) {
    $activeProviders = \rex_post('providers', [
        ['active', 'array', []]
    ]);
    
    // Speichern der aktiven Provider
    $addon->setConfig('active_providers', $activeProviders['active']);
    echo \rex_view::success(\rex_i18n::msg('file_importer_provider_status_saved'));
}

// Alle verfügbaren Provider
$providers = [
    'pixabay' => [
        'name' => \rex_i18n::msg('file_importer_provider_pixabay'),
        'description' => \rex_i18n::msg('file_importer_provider_pixabay_description'),
        'icon' => 'fa-image',
        'settings_url' => \rex_url::backendPage('file_importer/config')
    ],
    // Hier können später weitere Provider hinzugefügt werden
    /*'unsplash' => [
        'name' => \rex_i18n::msg('file_importer_provider_unsplash'),
        'description' => \rex_i18n::msg('file_importer_provider_unsplash_description'),
        'icon' => 'fa-camera',
        'settings_url' => \rex_url::backendPage('file_importer/config', ['provider' => 'unsplash'])
    ]*/
];

// Aktive Provider laden
$activeProviders = $addon->getConfig('active_providers', ['pixabay']);

$content = '';

// Provider Liste
$content .= '<div class="file-importer-providers">';
$content .= '<form action="' . \rex_url::currentBackendPage() . '" method="post">';

foreach ($providers as $id => $provider) {
    $isActive = in_array($id, $activeProviders);
    $isConfigured = isset($addon->providers[$id]) && $addon->providers[$id]->isConfigured();
    
    $content .= '
    <div class="panel panel-default">
        <header class="panel-heading">
            <div class="panel-title">
                <i class="rex-icon ' . $provider['icon'] . '"></i>
                ' . $provider['name'] . '
                
                <div class="pull-right">
                    <div class="rex-form-group checkbox">
                        <label>
                            <input type="checkbox" 
                                   name="providers[active][]" 
                                   value="' . $id . '"
                                   ' . ($isActive ? 'checked' : '') . '>
                            ' . \rex_i18n::msg('file_importer_provider_activate') . '
                        </label>
                    </div>
                </div>
            </div>
        </header>
        <div class="panel-body">
            <div class="row">
                <div class="col-md-8">
                    <p>' . $provider['description'] . '</p>
                </div>
                <div class="col-md-4 text-right">
                    <div class="provider-status">
                        ' . ($isConfigured 
                            ? '<span class="text-success"><i class="rex-icon fa-check"></i> ' . \rex_i18n::msg('file_importer_provider_configured') . '</span>'
                            : '<span class="text-danger"><i class="rex-icon fa-times"></i> ' . \rex_i18n::msg('file_importer_provider_not_configured') . '</span>'
                        ) . '
                    </div>
                    <a href="' . $provider['settings_url'] . '" class="btn btn-default btn-xs">
                        <i class="rex-icon fa-cog"></i> 
                        ' . \rex_i18n::msg('file_importer_provider_settings') . '
                    </a>
                </div>
            </div>
        </div>
    </div>';
}

// Submit Button
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save" type="submit" name="provider-status-submit" value="1">' 
    . \rex_i18n::msg('file_importer_provider_save_status') 
    . '</button>';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/submit.php');

$content .= '</form>';
$content .= '</div>';

// Styles
$content .= '
<style>
.file-importer-providers .panel-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.file-importer-providers .panel-title {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.file-importer-providers .checkbox {
    margin: 0;
}

.file-importer-providers .provider-status {
    margin-bottom: 10px;
}
</style>';

// Ausgabe Fragment
$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', \rex_i18n::msg('file_importer_providers'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
