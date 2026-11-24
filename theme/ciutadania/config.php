<?php
defined('MOODLE_INTERNAL') || die();

$THEME->name = 'ciutadania';
$THEME->author = 'Dani Espinosa';
$THEME->version = 2026112300;
$THEME->sheets = [];
$THEME->editor_sheets = [];
$THEME->parents = ['boost']; // <-- Aquí ocurre la magia
$THEME->enable_dock = false;
$THEME->yuicssmodules = [];
$THEME->rendererfactory = 'theme_overridden_renderer_factory';
// Cap bloc obligatori. Si no es defineix, Moodle força Navegació i Administració a totes
// les pàgines (els temes fills no hereten aquest valor de Boost, que també el té buit).
$THEME->requiredblocks = '';
// Variables SCSS que han d'anar abans de Boost (per sobreescriure els seus !default).
$THEME->prescsscallback = 'theme_ciutadania_get_pre_scss';
$THEME->scss = function($theme) {
    return theme_ciutadania_get_main_scss_content($theme);
};