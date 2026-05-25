<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'ciutadania';
$THEME->author = 'Dani Espinosa';
$THEME->version = 2026012900;
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->parents = ['boost']; // <-- Aquí ocurre la magia
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
$THEME->scss = function($theme) {
    return theme_ciutadania_get_main_scss_content($theme);
};