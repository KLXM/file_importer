<?php
namespace Klxm\FileImporter;

use rex_addon;
use rex_be_controller;
use rex_view;

$addon = rex_addon::get('file_importer');
echo rex_view::title($addon->i18n('title'));
rex_be_controller::includeCurrentPageSubPath();
