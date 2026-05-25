<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'customcertelement/approvedmodules:add' => [
        'riskbitmask' => RISK_SPAM,
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ]
    ]
];
