<?php
namespace Klxm\FileImporter;

$addon = \rex_addon::get('file_importer');
$content = '';

// Formular verarbeiten
if (\rex_post('config-submit', 'boolean')) {
    $configs = \rex_post('config', [
        ['pixabay_apikey', 'string'],
        ['items_per_page', 'int'],
        ['cache_lifetime', 'int']
    ]);
    
    // Validierung
    $errors = [];
    if (empty($configs['pixabay_apikey'])) {
        $errors[] = \rex_i18n::msg('file_importer_pixabay_apikey_missing');
    }
    
    if (empty($errors)) {
        // Konfiguration speichern
        $addon->setConfig('pixabay', [
            'apikey' => $configs['pixabay_apikey']
        ]);
        
        // Allgemeine Einstellungen
        $addon->setConfig('items_per_page', $configs['items_per_page']);
        $addon->setConfig('cache_lifetime', $configs['cache_lifetime']);
        
        // Cache leeren
        \rex_cache::delete('fileimporter.');
        
        echo \rex_view::success(\rex_i18n::msg('file_importer_config_saved'));
    } else {
        echo \rex_view::error(implode('<br>', $errors));
    }
}

// Cache leeren Button
if (\rex_post('clear-cache', 'boolean')) {
    \rex_cache::delete('fileimporter.');
    echo \rex_view::success(\rex_i18n::msg('file_importer_cache_cleared'));
}

// Formular erstellen
$content .= '<div class="rex-form">';
$content .= '<form action="' . \rex_url::currentBackendPage() . '" method="post">';

$formElements = [];

// Pixabay Einstellungen
$content .= '<fieldset>';
$content .= '<legend>' . \rex_i18n::msg('file_importer_provider_pixabay') . '</legend>';

// API Key
$n = [];
$n['label'] = '<label for="pixabay-apikey">' . \rex_i18n::msg('file_importer_pixabay_apikey') . '</label>';
$n['field'] = '<input type="text" 
                      id="pixabay-apikey" 
                      name="config[pixabay_apikey]" 
                      value="' . $addon->getConfig('pixabay')['apikey'] . '" 
                      class="form-control"/>';
$n['notice'] = \rex_i18n::msg('file_importer_pixabay_apikey_notice');
$formElements[] = $n;

// Status anzeigen
$provider = $addon->providers['pixabay'] ?? null;
if ($provider) {
    $status = $provider->isConfigured() 
        ? '<span class="text-success">' . \rex_i18n::msg('file_importer_provider_active') . '</span>'
        : '<span class="text-danger">' . \rex_i18n::msg('file_importer_provider_inactive') . '</span>';
    
    $n = [];
    $n['label'] = '<label>' . \rex_i18n::msg('file_importer_provider_status') . '</label>';
    $n['field'] = '<p class="form-control-static">' . $status . '</p>';
    $formElements[] = $n;
}

$content .= '</fieldset>';

// Allgemeine Einstellungen
$content .= '<fieldset><legend>' . \rex_i18n::msg('file_importer_settings') . '</legend>';

// Ergebnisse pro Seite
$n = [];
$n['label'] = '<label for="items-per-page">' . \rex_i18n::msg('file_importer_results_per_page') . '</label>';
$n['field'] = '<input type="number" 
                      id="items-per-page" 
                      name="config[items_per_page]" 
                      value="' . ($addon->getConfig('items_per_page') ?: 20) . '" 
                      min="10" 
                      max="50" 
                      class="form-control"/>';
$formElements[] = $n;

// Cache-Lebenszeit
$n = [];
$n['label'] = '<label for="cache-lifetime">' . \rex_i18n::msg('file_importer_cache_lifetime') . '</label>';
$n['field'] = '<input type="number" 
                      id="cache-lifetime" 
                      name="config[cache_lifetime]" 
                      value="' . ($addon->getConfig('cache_lifetime') ?: 86400) . '" 
                      min="3600" 
                      max="604800" 
                      class="form-control"/>';
$n['notice'] = \rex_i18n::msg('file_importer_cache_lifetime_notice');
$formElements[] = $n;

$content .= '</fieldset>';

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/form.php');

// Submit
$formElements = [];
$n = [];
$n['field'] = '<button class="btn btn-save rex-form-aligned" type="submit" name="config-submit" value="1">' 
    . \rex_i18n::msg('save') 
    . '</button>';
$formElements[] = $n;

// Cache leeren Button
$n = [];
$n['field'] = '<button class="btn btn-delete rex-form-aligned" type="submit" name="clear-cache" value="1">'
    . \rex_i18n::msg('file_importer_clear_cache')
    . '</button>';
$formElements[] = $n;

$fragment = new \rex_fragment();
$fragment->setVar('elements', $formElements, false);
$content .= $fragment->parse('core/form/submit.php');

$content .= '</form>';
$content .= '</div>';

// Ausgabe Fragment
$fragment = new \rex_fragment();
$fragment->setVar('class', 'edit');
$fragment->setVar('title', \rex_i18n::msg('file_importer_settings'));
$fragment->setVar('body', $content, false);
echo $fragment->parse('core/page/section.php');
