<?php
namespace customcertelement_hourscalculated;

defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {
    public function render_form_elements($mform) {
        parent::render_form_elements($mform);
    }

    public function render($pdf, $preview, $user) {
        $hours = $this->get_calculated_hours($user);
        $text = $hours . " h";
        \mod_customcert\element_helper::render_content($pdf, $this, $text);
    }

    public function render_html() {
        global $USER;
        $hours = $this->get_calculated_hours($USER);
        $text = $hours . " h";
        return \mod_customcert\element_helper::render_html_content($this, $text);
    }

    public function save_unique_data($data) {
        return json_encode([]);
    }

    protected function get_calculated_hours($user) {
        global $COURSE;
        if (empty($user->id)) return 0;
        $completion = new \completion_info($COURSE);
        $modinfo = get_fast_modinfo($COURSE->id);
        $count = 0;
        foreach ($modinfo->cms as $cm) {
            if ($cm->completion == COMPLETION_TRACKING_NONE) continue;
            $data = $completion->get_data($cm, true, $user->id);
            if ($data->completionstate == COMPLETION_COMPLETE || $data->completionstate == COMPLETION_COMPLETE_PASS) {
                $count++;
            }
        }
        return $count * 2;
    }
}