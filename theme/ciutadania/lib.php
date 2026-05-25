<?php
function theme_ciutadania_get_main_scss_content($theme) {
    global $CFG;
    $scss = '';
    
    // Aquí defines tus colores corporativos
    $primary = '#50488E'; // Cambia esto por tu azul/color principal
    $success = '#28a745';

    // Leemos el archivo SCSS principal de Boost y le añadimos lo nuestro
    $filename = $CFG->dirroot . '/theme/boost/scss/preset/default.scss';
    $scss .= file_get_contents($filename);

    $scssfiles = [
        'custom',
        'custom.login.page',
        'custom.policy.page',
        'custom.signup.page',
        'custom.my.index.page',
        'custom.inici.page',
        'custom.enroll.index.page',
        'custom.cursos.page',
        'custom.curs.page',
        'custom.scorm',
    ];

    $scssbase = $CFG->dirroot . '/theme/ciutadania/scss/';
    foreach ($scssfiles as $file) {
        $filepath = $scssbase . $file . '.scss';
        if (file_exists($filepath)) {
            $scss .= file_get_contents($filepath);
        }
    }

    return $scss;
}