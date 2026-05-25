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
        global $COURSE;

        if (empty($user->id)) {
            return [];
        }

        // Get mode from saved data
        $data = json_decode($this->get_data(), true);
        $mode = !empty($data['mode']) ? $data['mode'] : 'current';

        // If mode is 'certified', get modules from certification snapshot
        if ($mode === 'certified' && class_exists('\local_ciudadania_certs\snapshot_manager')) {
            $certifiedmodules = \local_ciudadania_certs\snapshot_manager::get_certified_modules($user->id, $COURSE->id);
            if (!empty($certifiedmodules)) {
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
        }

        // Default: get current approved modules using snapshot_manager logic.
        $raw = \local_ciudadania_certs\snapshot_manager::get_current_approved_modules($user->id, $COURSE->id);
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
     * Formats the modules list as an HTML table with 4 columns.
     *
     * @param array $modules Array of module objects
     * @return string HTML table
     */
    protected function format_modules_list($modules) {
        if (empty($modules)) {
            return get_string('nomodulesapproved', 'customcertelement_approvedmodules');
        }

        $rows = '';
        foreach ($modules as $module) {
            $date = $module->timemodified
                ? date('d.m.Y', $module->timemodified)
                : '-';
            $grade10 = number_format($module->grade / 10, 1);
            $rows .= '<tr>'
                . '<td>' . htmlspecialchars($module->name) . '</td>'
                . '<td>2h</td>'
                . '<td>' . $date . '</td>'
                . '<td>' . $grade10 . '/10</td>'
                . '</tr>';
        }

        return '<table border="0" cellpadding="2" cellspacing="0">'
            . $rows
            . '</table>';
    }
}
