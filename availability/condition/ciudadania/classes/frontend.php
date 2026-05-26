<?php
namespace availability_ciudadania;

defined('MOODLE_INTERNAL') || die();

class frontend extends \core_availability\frontend {

    protected function get_javascript_strings() {
        return [];
    }

    protected function get_javascript_init_params($course, ?\cm_info $cm = null,
            ?\section_info $section = null) {
        return [];
    }

    protected function allow_add($course, ?\cm_info $cm = null,
            ?\section_info $section = null) {
        return true;
    }
}
