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

        return $DB->insert_record('ciudadania_certifications', $record);
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
     *   - A module is approved if its grade >= 35/100.
     *   - The average of ALL completed modules (regardless of grade) must be > 50/100;
     *     if not, the overall requirement is not met and an empty array is returned.
     *
     * @param int $userid User ID
     * @param int $courseid Course ID
     * @return array Array of module arrays with keys: cmid, name, grade, timemodified
     */
    public static function get_current_approved_modules($userid, $courseid) {
        global $DB;

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
                'name'         => $cm->name,
                'grade'        => round($grade->finalgrade, 0),
                'timemodified' => $data->timemodified ?? 0,
            ];
        }

        if (empty($completedmodules)) {
            return [];
        }

        // Check that the average of ALL completed modules exceeds 50/100.
        $avg = array_sum(array_column($completedmodules, 'grade')) / count($completedmodules);
        if ($avg <= 50) {
            return [];
        }

        // Return only modules with grade >= 35/100.
        return array_values(array_filter($completedmodules, fn($m) => $m['grade'] >= 35));
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
}
