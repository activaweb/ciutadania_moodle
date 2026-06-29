<?php
namespace customcertelement_texttemplate;

defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {

    public function render_form_elements($mform) {
        $mform->addElement('textarea', 'template',
            get_string('template', 'customcertelement_texttemplate'),
            ['rows' => 6, 'cols' => 60]
        );
        $mform->setType('template', PARAM_RAW);
        $mform->addHelpButton('template', 'template', 'customcertelement_texttemplate');

        parent::render_form_elements($mform);
    }

    public function save_unique_data($data) {
        return json_encode(['template' => $data->template ?? '']);
    }

    public function render($pdf, $preview, $user) {
        $text = $this->get_rendered_text($user);
        \mod_customcert\element_helper::render_content($pdf, $this, $text);
    }

    public function render_html() {
        global $USER;
        $text = $this->get_rendered_text($USER);
        return \mod_customcert\element_helper::render_html_content($this, $text);
    }

    /**
     * Resolves the template text replacing all variables with actual values.
     */
    protected function get_rendered_text($user) {
        global $COURSE;

        $data = json_decode($this->get_data(), true);
        $template = $data['template'] ?? '';

        if (empty($template)) {
            return '';
        }

        $snapshot = $this->get_snapshot_data($user->id, $COURSE->id);

        if ($snapshot === null) {
            return get_string('nopaymentmade', 'customcertelement_texttemplate');
        }

        $vars = $this->build_vars($user, $COURSE, $snapshot);

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    /**
     * Returns snapshot data for this user, or null if no payment has been made.
     * Falls back to current approved modules in preview mode.
     */
    protected function get_snapshot_data($userid, $courseid) {
        if (!class_exists('\local_ciudadania_certs\snapshot_manager')) {
            return null;
        }

        $certified = \local_ciudadania_certs\snapshot_manager::get_certified_modules($userid, $courseid);

        if (!empty($certified)) {
            $grades = array_column($certified, 'grade');
            return [
                'modules'    => $certified,
                'total'      => count($certified),
                'hours'      => count($certified) * 2,
                'avg'        => array_sum($grades) / count($grades),
                'timecreated'=> \local_ciudadania_certs\snapshot_manager::get_last_snapshot_time($userid, $courseid),
            ];
        }

        // Preview fallback: use current approved modules.
        $current = \local_ciudadania_certs\snapshot_manager::get_current_approved_modules($userid, $courseid);
        if (!empty($current)) {
            $grades = array_column($current, 'grade');
            return [
                'modules'    => $current,
                'total'      => count($current),
                'hours'      => count($current) * 2,
                'avg'        => array_sum($grades) / count($grades),
                'timecreated'=> time(),
            ];
        }

        return null;
    }

    /**
     * Builds the variable map for template substitution.
     */
    protected function build_vars($user, $course, $snapshot) {
        $certdate = !empty($snapshot['timecreated'])
            ? userdate($snapshot['timecreated'], get_string('strftimedate', 'langconfig'))
            : '-';

        $today = userdate(time(), get_string('strftimedate', 'langconfig'));

        return [
            '{nom_complet}'     => fullname($user),
            '{nom}'             => $user->firstname,
            '{cognoms}'         => $user->lastname,
            '{total_moduls}'    => $snapshot['total'],
            '{nota_mitja}'      => number_format($snapshot['avg'], 1),
            '{total_hores}'     => $snapshot['hours'],
            '{nom_curs}'        => format_string($course->fullname),
            '{data_certificat}' => $certdate,
            '{data_avui}'       => $today,
        ];
    }
}
