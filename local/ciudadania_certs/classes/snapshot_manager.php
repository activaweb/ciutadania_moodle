<?php
namespace local_ciudadania_certs;

defined('MOODLE_INTERNAL') || die();

/**
 * Manager class for certification snapshots.
 */
class snapshot_manager {

    /**
     * Create a new certification snapshot for a user.
     * Captures the current state of approved modules (grade >= 70).
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $paymentcmid Course module ID of the payment activity
     * @return int|false The ID of the created snapshot, or false on failure
     */
    public static function create_snapshot($userid, $courseid, $paymentcmid) {
        global $DB;

        $modules = self::get_current_approved_modules($userid, $courseid);

        if (empty($modules)) {
            return false;
        }

        // Delete previous snapshots for this user+course before inserting the new one.
        $DB->delete_records('ciudadania_certifications', ['userid' => $userid, 'courseid' => $courseid]);

        $record = new \stdClass();
        $record->userid = $userid;
        $record->courseid = $courseid;
        $record->paymentcmid = $paymentcmid;
        $record->modules_json = json_encode($modules);
        $record->total_modules = count($modules);
        $record->total_hours = count($modules) * 2;
        $record->timecreated = time();

        $result = $DB->insert_record('ciudadania_certifications', $record);

        if ($result) {
            self::update_certificate_issue_date($userid, $courseid, $record->timecreated);
        }

        return $result;
    }

    /**
     * Refreshes the "issued date" of the official certificate (mod_customcert instance
     * whose "approvedmodules" element is configured in "certified" mode) so that it
     * reflects the latest payment instead of staying frozen at the first one.
     *
     * mod_customcert only creates a customcert_issues row the first time a user opens
     * the certificate (see mod/customcert/view.php) and never touches it again on
     * subsequent visits, so its "timecreated" (used by the "date" element configured
     * as "issued date") would otherwise always show the date of the first certification.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @param int $time Timestamp to set as the new issue date
     */
    private static function update_certificate_issue_date($userid, $courseid, $time) {
        global $DB;

        $customcertid = $DB->get_field_sql(
            "SELECT c.id
               FROM {customcert} c
               JOIN {customcert_templates} t ON t.id = c.templateid
               JOIN {customcert_pages} p ON p.templateid = t.id
               JOIN {customcert_elements} e ON e.pageid = p.id
              WHERE c.course = :courseid
                AND e.element = 'approvedmodules'
                AND " . $DB->sql_like('e.data', ':mode') . "
              LIMIT 1",
            ['courseid' => $courseid, 'mode' => '%"mode":"certified"%']
        );

        if (!$customcertid) {
            return;
        }

        // Only touch an issue that already exists; if the student hasn't opened the
        // certificate yet, mod_customcert will create it with the correct time itself.
        $DB->set_field('customcert_issues', 'timecreated', $time, [
            'userid' => $userid,
            'customcertid' => $customcertid,
        ]);
    }

    /**
     * Get the most recent certified modules for a user in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Array of module objects with 'name' and 'grade'
     */
    public static function get_certified_modules($userid, $courseid) {
        global $DB;

        $snapshot = $DB->get_record_sql(
            "SELECT * FROM {ciudadania_certifications}
             WHERE userid = :userid AND courseid = :courseid
             ORDER BY timecreated DESC
             LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        if (!$snapshot) {
            return [];
        }

        $modules = json_decode($snapshot->modules_json, true);
        return $modules ? $modules : [];
    }

    /**
     * Get all certification snapshots for a user in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Array of snapshot records
     */
    public static function get_all_snapshots($userid, $courseid) {
        global $DB;

        return $DB->get_records('ciudadania_certifications',
            ['userid' => $userid, 'courseid' => $courseid],
            'timecreated DESC'
        );
    }

    /**
     * Get currently approved modules for a user.
     *
     * Rules:
     *   - Only completed modules count (non-completed are ignored entirely).
     *   - Mandatory modules M1, M2, M3, M4, M10 (by idnumber) must all be completed
     *     with grade >= 35/100; otherwise an empty array is returned.
     *   - The average of eligible modules (grade >= 35/100) must be >= 70/100;
     *     if not, an empty array is returned.
     *   - Returns only modules with grade >= 35/100.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Array of module arrays with keys: cmid, idnumber, name, grade, timemodified
     */
    public static function get_current_approved_modules($userid, $courseid) {
        global $DB;

        $mandatory_idnumbers = ['M1', 'M2', 'M3', 'M4', 'M10'];

        $course = get_course($courseid);
        $completion = new \completion_info($course);
        $modinfo = get_fast_modinfo($courseid);

        $gradeitems = $DB->get_records('grade_items', [
            'courseid' => $courseid,
            'itemtype' => 'mod',
        ]);

        $completedmodules = [];

        foreach ($modinfo->cms as $cm) {
            if ($cm->completion == COMPLETION_TRACKING_NONE) {
                continue;
            }

            $data = $completion->get_data($cm, true, $userid);
            if ($data->completionstate != COMPLETION_COMPLETE &&
                $data->completionstate != COMPLETION_COMPLETE_PASS) {
                continue;
            }

            $gradeitem = null;
            foreach ($gradeitems as $gi) {
                if ($gi->itemmodule == $cm->modname && $gi->iteminstance == $cm->instance) {
                    $gradeitem = $gi;
                    break;
                }
            }

            if (!$gradeitem) {
                continue;
            }

            $grade = $DB->get_record('grade_grades', [
                'itemid' => $gradeitem->id,
                'userid' => $userid,
            ]);

            if (!$grade || is_null($grade->finalgrade)) {
                continue;
            }

            $completedmodules[] = [
                'cmid'         => $cm->id,
                'idnumber'     => $cm->idnumber,
                'name'         => $cm->name,
                'grade'        => round($grade->finalgrade, 0),
                'timemodified' => $data->timemodified ?? 0,
            ];
        }

        if (empty($completedmodules)) {
            return [];
        }

        // Eligible modules: grade >= 35/100.
        $eligible = array_values(array_filter($completedmodules, fn($m) => $m['grade'] >= 35));

        if (empty($eligible)) {
            return [];
        }

        // All mandatory modules must be present and eligible (grade >= 35).
        $eligible_idnumbers = array_column($eligible, 'idnumber');
        foreach ($mandatory_idnumbers as $mid) {
            if (!in_array($mid, $eligible_idnumbers)) {
                return [];
            }
        }

        // Average of eligible modules must be >= 70/100.
        $avg = array_sum(array_column($eligible, 'grade')) / count($eligible);
        if ($avg < 70) {
            return [];
        }

        return $eligible;
    }

    /**
     * Check if a user has any certifications in a course.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return bool True if user has at least one certification
     */
    public static function has_certifications($userid, $courseid) {
        global $DB;

        return $DB->record_exists('ciudadania_certifications', [
            'userid' => $userid,
            'courseid' => $courseid
        ]);
    }

    /**
     * Get the timestamp of the most recent certification snapshot.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return int|null Timestamp or null if no snapshot exists
     */
    public static function get_last_snapshot_time($userid, $courseid) {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT timecreated FROM {ciudadania_certifications}
             WHERE userid = :userid AND courseid = :courseid
             ORDER BY timecreated DESC
             LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        return $record ? (int)$record->timecreated : null;
    }

    /**
     * Get the course module ID of the payment activity used for the most recent snapshot.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return int|null The cmid, or null if no snapshot exists
     */
    public static function get_last_payment_cmid($userid, $courseid) {
        global $DB;

        $record = $DB->get_record_sql(
            "SELECT paymentcmid FROM {ciudadania_certifications}
             WHERE userid = :userid AND courseid = :courseid
             ORDER BY timecreated DESC
             LIMIT 1",
            ['userid' => $userid, 'courseid' => $courseid]
        );

        return $record ? (int)$record->paymentcmid : null;
    }
}
