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

    public function define_defaults($mform) {
        $data = json_decode($this->get_data(), true);
        if (isset($data['template'])) {
            $mform->setDefault('template', $data['template']);
        }
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
                'modules'     => $certified,
                'total'       => count($certified),
                'hours'       => count($certified) * 2,
                'avg'         => array_sum($grades) / count($grades),
                'timecreated' => \local_ciudadania_certs\snapshot_manager::get_last_snapshot_time($userid, $courseid),
            ];
        }

        // Preview fallback: use current approved modules.
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
