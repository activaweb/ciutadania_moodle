<?php
defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    // Hem tret el guió baix de hours_calculated per hourscalculated
    'customcertelement/hourscalculated:add' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => array(
            'editingteacher' => CAP_ALLOW,
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'mod/customcert:addinstance'
    ),
);