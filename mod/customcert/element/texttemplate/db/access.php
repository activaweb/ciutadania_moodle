<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'customcertelement/texttemplate:addinstance' => [
        'captype'      => 'write',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes'   => [
            'editingteacher' => CAP_ALLOW,
            'manager'        => CAP_ALLOW,
        ],
    ],
];
