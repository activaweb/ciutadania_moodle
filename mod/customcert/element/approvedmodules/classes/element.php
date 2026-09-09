<?php
namespace customcertelement_approvedmodules;

defined('MOODLE_INTERNAL') || die();

class element extends \mod_customcert\element {

    /**
     * Renders the form elements for this element.
     */
    public function render_form_elements($mform) {
        // Add mode selector (current vs certified)
        $mform->addElement('select', 'mode', get_string('mode', 'customcertelement_approvedmodules'), [
            'current' => get_string('mode_current', 'customcertelement_approvedmodules'),
            'certified' => get_string('mode_certified', 'customcertelement_approvedmodules')
        ]);
        $mform->setDefault('mode', 'current');
        $mform->addHelpButton('mode', 'mode', 'customcertelement_approvedmodules');

        // Add option to show grades alongside module names
        $mform->addElement('selectyesno', 'showgrades', get_string('showgrades', 'customcertelement_approvedmodules'));
        $mform->setDefault('showgrades', 0);
        $mform->addHelpButton('showgrades', 'showgrades', 'customcertelement_approvedmodules');

        parent::render_form_elements($mform);
    }

    /**
     * Pre-fills the form with the previously saved values.
     *
     * Without this, the "mode" and "showgrades" selects always fall back to their
     * form defaults when editing an existing element, so simply opening and
     * re-saving an already-configured element (e.g. one set to "certified" for
     * the official certificate) would silently reset it to "current".
     */
    public function definition_after_data($mform) {
        $data = json_decode($this->get_data(), true);
        if (!empty($data['mode'])) {
            $mform->getElement('mode')->setValue($data['mode']);
        }
        if (isset($data['showgrades'])) {
            $mform->getElement('showgrades')->setValue($data['showgrades']);
        }
        parent::definition_after_data($mform);
    }

    /**
     * Renders this element on the PDF.
     */
    public function render($pdf, $preview, $user) {
        $modules = $this->get_approved_modules($user);
        $text = $this->format_modules_list($modules);

        \mod_customcert\element_helper::render_content($pdf, $this, $text);
    }

    /**
     * Renders this element in HTML preview.
     */
    public function render_html() {
        global $USER;
        $modules = $this->get_approved_modules($USER);
        $text = $this->format_modules_list($modules);

        return \mod_customcert\element_helper::render_html_content($this, $text);
    }

    /**
     * Saves unique data for this element.
     */
    public function save_unique_data($data) {
        $arrtostore = [
            'mode' => !empty($data->mode) ? $data->mode : 'current',
            'showgrades' => !empty($data->showgrades) ? 1 : 0
        ];
        return json_encode($arrtostore);
    }

    /**
     * Gets the approved modules for a user.
     *
     * @param stdClass $user The user object
     * @return array Array of module objects with name, grade, and timemodified
     */
    protected function get_approved_modules($user) {
        if (empty($user->id)) {
            return [];
        }

        $courseid = $this->get_courseid();

        // Get mode from saved data
        $data = json_decode($this->get_data(), true);
        $mode = !empty($data['mode']) ? $data['mode'] : 'current';

        // If mode is 'certified', get modules from certification snapshot.
        // Returns null (not empty array) when no payment has been made yet,
        // so the renderer can show a specific "pending payment" message.
        if ($mode === 'certified' && class_exists('\local_ciudadania_certs\snapshot_manager')) {
            $certifiedmodules = \local_ciudadania_certs\snapshot_manager::get_certified_modules($user->id, $courseid);

            if (empty($certifiedmodules)) {
                return null;
            }

            $modules = [];
            foreach ($certifiedmodules as $module) {
                $modules[] = (object)[
                    'name'         => $module['name'],
                    'grade'        => $module['grade'],
                    'timemodified' => $module['timemodified'] ?? 0,
                ];
            }
            return $modules;
        }

        // Default: get current approved modules using snapshot_manager logic.
        $raw = \local_ciudadania_certs\snapshot_manager::get_current_approved_modules($user->id, $courseid);
        $approvedmodules = [];
        foreach ($raw as $m) {
            $approvedmodules[] = (object)[
                'name'         => $m['name'],
                'grade'        => $m['grade'],
                'timemodified' => $m['timemodified'] ?? 0,
            ];
        }

        return $approvedmodules;
    }

    /**
     * Returns the id of the course this element's certificate belongs to.
     *
     * Deliberately avoids the global $COURSE, which other code invoked during PDF
     * generation (e.g. rendering an earlier element on the same page) can leave
     * pointing at the wrong course by the time this element renders.
     */
    protected function get_courseid() {
        global $DB;

        return $DB->get_field_sql(
            "SELECT cc.course
               FROM {customcert_pages} cp
               JOIN {customcert_templates} ct ON ct.id = cp.templateid
               JOIN {customcert} cc ON cc.templateid = ct.id
              WHERE cp.id = :pageid",
            ['pageid' => $this->get_pageid()],
            MUST_EXIST
        );
    }

    /**
     * Formats the modules list as an HTML table with 4 columns.
     *
     * @param array $modules Array of module objects
     * @return string HTML table
     */
    protected function format_modules_list($modules) {
        if ($modules === null) {
            return get_string('nopaymentmade', 'customcertelement_approvedmodules');
        }

        if (empty($modules)) {
            return get_string('nomodulesapproved', 'customcertelement_approvedmodules');
        }

        // TCPDF's HTML table engine does not auto-fit column widths to content: any
        // column without an explicit width just gets an equal share of the table
        // width, it does not inherit whatever space the other columns leave over.
        // So the "name column takes the rest" effect is approximated with fixed
        // percentages instead (name column gets the largest share, the other three
        // get just enough for their short, fixed-format content).
        $namestyle = 'text-align:left; padding:0 2px;';
        $colstyle = 'text-align:center; padding:0 10px; white-space:nowrap;';

        $header = '<tr>'
            . '<th width="45%" style="' . $namestyle . '"><b>Assignatura</b></th>'
            . '<th width="15%" style="' . $colstyle . '"><b>Hores reglades</b></th>'
            . '<th width="17%" style="' . $colstyle . '"><b>Data finalització</b></th>'
            . '<th width="13%" style="' . $colstyle . '"><b>Qualificació</b></th>'
            . '</tr>';

        $rows = '';
        foreach ($modules as $module) {
            $date = $module->timemodified
                ? date('d.m.Y', $module->timemodified)
                : '-';
            $grade10 = number_format($module->grade / 10, 1);
            $rows .= '<tr>'
                . '<td width="45%" style="' . $namestyle . '">' . htmlspecialchars($module->name) . '</td>'
                . '<td width="15%" style="' . $colstyle . '">2h</td>'
                . '<td width="17%" style="' . $colstyle . '">' . $date . '</td>'
                . '<td width="13%" style="' . $colstyle . '">' . $grade10 . '/10</td>'
                . '</tr>';
        }

        return '<table border="0" cellpadding="0" cellspacing="0">'
            . $header
            . $rows
            . '</table>';
    }
}
