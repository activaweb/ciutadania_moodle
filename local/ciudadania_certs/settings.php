<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_ciudadania_certs', get_string('pluginname', 'local_ciudadania_certs'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtextarea(
        'local_ciudadania_certs/mencions_definition',
        get_string('mencions_definition', 'local_ciudadania_certs'),
        get_string('mencions_definition_help', 'local_ciudadania_certs'),
        \local_ciudadania_certs\mencions_helper::DEFAULT_DEFINITION,
        PARAM_RAW
    ));
}
