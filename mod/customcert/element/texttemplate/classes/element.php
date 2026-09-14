<?php
namespace customcertelement_texttemplate;

defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {

    public function render_form_elements($mform) {
        $mform->addElement('select', 'mode', get_string('mode', 'customcertelement_texttemplate'), [
            'current' => get_string('mode_current', 'customcertelement_texttemplate'),
            'certified' => get_string('mode_certified', 'customcertelement_texttemplate'),
        ]);
        $mform->setDefault('mode', 'certified');
        $mform->addHelpButton('mode', 'mode', 'customcertelement_texttemplate');

        $mform->addElement('textarea', 'template',
            get_string('template', 'customcertelement_texttemplate'),
            ['rows' => 6, 'cols' => 60]
        );
        $mform->setType('template', PARAM_RAW);
        $mform->addHelpButton('template', 'template', 'customcertelement_texttemplate');

        parent::render_form_elements($mform);
    }

    public function definition_after_data($mform) {
        $data = json_decode($this->get_data(), true);
        if (!empty($data['template'])) {
            $element = $mform->getElement('template');
            $element->setValue($data['template']);
        }
        if (!empty($data['mode'])) {
            $mform->getElement('mode')->setValue($data['mode']);
        }
        parent::definition_after_data($mform);
    }

    public function save_unique_data($data) {
        return json_encode([
            'template' => $data->template ?? '',
            'mode' => !empty($data->mode) ? $data->mode : 'certified',
        ]);
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
        $data = json_decode($this->get_data(), true);
        $template = $data['template'] ?? '';

        if (empty($template)) {
            return '';
        }

        $course = $this->get_course();
        $mode = !empty($data['mode']) ? $data['mode'] : 'certified';
        $snapshot = $this->get_snapshot_data($user->id, $course->id, $mode);

        $vars = $this->build_vars($user, $course, $snapshot);

        return str_replace(array_keys($vars), array_values($vars), $template);
    }

    /**
     * Returns the course this element's certificate belongs to.
     *
     * Deliberately avoids the global $COURSE, which other code invoked during PDF
     * generation (e.g. rendering an earlier element on the same page) can leave
     * pointing at the wrong course by the time this element renders.
     */
    protected function get_course() {
        global $DB;

        $courseid = $DB->get_field_sql(
            "SELECT cc.course
               FROM {customcert_pages} cp
               JOIN {customcert_templates} ct ON ct.id = cp.templateid
               JOIN {customcert} cc ON cc.templateid = ct.id
              WHERE cp.id = :pageid",
            ['pageid' => $this->get_pageid()],
            MUST_EXIST
        );

        return get_course($courseid);
    }

    /**
     * Returns snapshot data for this user, or null if there is nothing to show.
     *
     * Mode "current" always reflects live progress (for a diploma of attendance
     * that must update as soon as a module is completed, regardless of payment).
     * Mode "certified" (default, for backwards compatibility with elements saved
     * before the mode selector existed) prefers the last paid snapshot, falling
     * back to current approved modules only when no payment has been made yet
     * (e.g. template previews).
     */
    protected function get_snapshot_data($userid, $courseid, $mode = 'certified') {
        if (!class_exists('\local_ciudadania_certs\snapshot_manager')) {
            return null;
        }

        if ($mode !== 'current') {
            $certified = \local_ciudadania_certs\snapshot_manager::get_certified_modules($userid, $courseid);
            if (!empty($certified)) {
                $grades = array_column($certified, 'grade');
                return [
                    'modules'     => $certified,
                    'total'       => count($certified),
                    'hours'       => count($certified) * 2,
                    'avg'         => array_sum($grades) / count($grades),
                    'timecreated' => \local_ciudadania_certs\snapshot_manager::get_last_snapshot_time($userid, $courseid),
                ];
            }
        }

        $current = \local_ciudadania_certs\snapshot_manager::get_current_approved_modules($userid, $courseid);
        if (!empty($current)) {
            $grades = array_column($current, 'grade');
            return [
                'modules'     => $current,
                'total'       => count($current),
                'hours'       => count($current) * 2,
                'avg'         => array_sum($grades) / count($grades),
                'timecreated' => time(),
            ];
        }

        return null;
    }

    /**
     * Builds the variable map for template substitution.
     */
    protected function build_vars($user, $course, $snapshot) {
        $datefmt = get_string('strftimedate', 'langconfig');

        $certdate = !empty($snapshot['timecreated'])
            ? userdate($snapshot['timecreated'], $datefmt)
            : '-';

        return [
            '{nom_complet}'                        => fullname($user),
            '{nom}'                                => $user->firstname,
            '{cognoms}'                            => $user->lastname,
            '{dni_nif}'                            => !empty($user->idnumber) ? $user->idnumber : '-',
            '{total_moduls}'                       => $snapshot ? $snapshot['total'] : '-',
            '{nota_mitja}'                         => $snapshot ? number_format($snapshot['avg'], 1) : '-',
            '{total_hores}'                        => $snapshot ? $snapshot['hours'] : '-',
            '{nom_curs}'                           => format_string($course->fullname),
            '{data_inici_curs}'                    => $this->get_enrolment_date($user->id, $course->id),
            '{data_finalitzacio_modul_mes_recent}' => $snapshot ? $this->get_latest_completion_date($snapshot) : '-',
            '{numero_referencia_certificat}'       => $snapshot ? $this->get_cert_reference($user->id, $course->id) : '-',
            '{mencions}'                           => $snapshot ? $this->get_mencions_text($snapshot, $course->id) : '-',
            '{data_emissio}'                       => $certdate,
            '{data_certificat}'                    => $certdate,
            '{data_avui}'                          => userdate(time(), $datefmt),
        ];
    }

    /**
     * Returns the course enrolment start date for the user.
     * Uses timecreated as fallback when timestart is 0 (no restriction set).
     */
    protected function get_enrolment_date($userid, $courseid) {
        global $DB;

        $sql = "SELECT MIN(CASE WHEN ue.timestart > 0 THEN ue.timestart ELSE ue.timecreated END) AS startdate
                FROM {user_enrolments} ue
                JOIN {enrol} e ON e.id = ue.enrolid
                WHERE ue.userid = :userid AND e.courseid = :courseid";

        $record = $DB->get_record_sql($sql, ['userid' => $userid, 'courseid' => $courseid]);

        if ($record && !empty($record->startdate)) {
            return userdate((int)$record->startdate, get_string('strftimedate', 'langconfig'));
        }

        return '-';
    }

    /**
     * Returns the date of the most recently completed module in the snapshot.
     */
    protected function get_latest_completion_date($snapshot) {
        if (empty($snapshot['modules'])) {
            return '-';
        }

        $times = array_filter(array_column($snapshot['modules'], 'timemodified'));

        if (empty($times)) {
            return '-';
        }

        return userdate(max($times), get_string('strftimedate', 'langconfig'));
    }

    /**
     * Returns the comma-separated list of mencions/itineraris earned by the modules
     * in the given snapshot.
     */
    protected function get_mencions_text($snapshot, $courseid) {
        if (!class_exists('\local_ciudadania_certs\mencions_helper')) {
            return '-';
        }

        $mencions = \local_ciudadania_certs\mencions_helper::get_earned_mencions($snapshot['modules'], $courseid);
        return implode(', ', $mencions);
    }

    /**
     * Returns a unique certificate reference number based on the snapshot record.
     * Format: CERT-{YYYY}-{id zero-padded to 5 digits}
     */
    protected function get_cert_reference($userid, $courseid) {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT id, timecreated FROM {ciudadania_certifications}
             WHERE userid = :userid AND courseid = :courseid
             ORDER BY timecreated DESC
             LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        if (!$record) {
            return '-';
        }

        return sprintf('CERT-%s-%05d', date('Y', $record->timecreated), $record->id);
    }
}
