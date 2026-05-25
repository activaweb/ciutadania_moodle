<?php
namespace local_ciudadania_certs;

defined('MOODLE_INTERNAL') || die();

class observer {

    /**
     * Handles course module completion events.
     *
     * Two behaviours:
     *   1. If the completed activity is a payment URL → create certification snapshot.
     *   2. If the completed activity is any other module with sufficient grade → reset
     *      the "URL tornada" completion so the student can pay again.
     */
    public static function course_module_completed(\core\event\course_module_completion_updated $event) {
        $data = $event->get_record_snapshot('course_modules_completion', $event->objectid);

        if ($data->completionstate != COMPLETION_COMPLETE &&
            $data->completionstate != COMPLETION_COMPLETE_PASS) {
            return;
        }

        $cm = get_coursemodule_from_id('', $data->coursemoduleid);
        if (!$cm) {
            return;
        }

        if (self::is_payment_url($cm)) {
            self::handle_payment($data, $cm);
        } else {
            self::maybe_reset_tornada($data, $cm);
        }
    }

    // -------------------------------------------------------------------------
    // Payment handling
    // -------------------------------------------------------------------------

    /**
     * Creates a certification snapshot when the student completes a payment URL.
     * Skipped if there are no new approved modules since the last snapshot.
     */
    private static function handle_payment($data, $cm) {
        $lastcertified = snapshot_manager::get_certified_modules($data->userid, $cm->course);

        if (!empty($lastcertified)) {
            $lastcmids     = array_column($lastcertified, 'cmid');
            $currentmodules = snapshot_manager::get_current_approved_modules($data->userid, $cm->course);
            $newcmids      = array_diff(array_column($currentmodules, 'cmid'), $lastcmids);

            if (empty($newcmids)) {
                mtrace("No new approved modules for user {$data->userid} in course {$cm->course}, skipping snapshot.");
                return;
            }
        }

        $result = snapshot_manager::create_snapshot($data->userid, $cm->course, $cm->id);

        if ($result) {
            mtrace("Certification snapshot created for user {$data->userid} in course {$cm->course}.");
        }
    }

    /**
     * Returns true if the course module is a URL activity identified as a payment gateway.
     */
    private static function is_payment_url($cm) {
        global $DB;

        if ($cm->modname !== 'url') {
            return false;
        }

        $url = $DB->get_record('url', ['id' => $cm->instance]);
        if (!$url) {
            return false;
        }

        return (
            stripos($url->externalurl, 'pagament') !== false ||
            stripos($url->externalurl, 'payment')  !== false ||
            stripos($url->name, 'pago')             !== false ||
            stripos($url->name, 'pagament')         !== false ||
            stripos($url->name, 'payment')          !== false ||
            stripos($url->name, 'certificat oficial') !== false
        );
    }

    // -------------------------------------------------------------------------
    // Tornada reset
    // -------------------------------------------------------------------------

    /**
     * After a regular module is completed, resets the "URL tornada" completion
     * so the student can initiate a new payment round.
     *
     * Only resets when:
     *   - The student already has at least one previous snapshot (has paid before).
     *   - The just-completed module is not already in the last snapshot.
     *   - The global grade conditions are still met (average > 50, module ≥ 35).
     */
    private static function maybe_reset_tornada($data, $cm) {
        $lastcertified = snapshot_manager::get_certified_modules($data->userid, $cm->course);

        // No previous snapshot → first payment flow, tornada is already not completed.
        if (empty($lastcertified)) {
            return;
        }

        // Module already in the last snapshot → nothing new to certify.
        if (in_array($cm->id, array_column($lastcertified, 'cmid'))) {
            return;
        }

        // Global grade conditions not met yet.
        $approved = snapshot_manager::get_current_approved_modules($data->userid, $cm->course);
        if (empty($approved)) {
            return;
        }

        self::reset_tornada_completion($data->userid, $cm->course);
    }

    /**
     * Finds the "URL tornada" activity in the course and resets its completion
     * for the given user, unlocking the payment button.
     */
    private static function reset_tornada_completion($userid, $courseid) {
        global $DB;

        $modinfo = get_fast_modinfo($courseid);
        $course  = get_course($courseid);
        $completion = new \completion_info($course);

        foreach ($modinfo->cms as $tornadacm) {
            if ($tornadacm->modname !== 'url') {
                continue;
            }

            $url = $DB->get_record('url', ['id' => $tornadacm->instance]);
            if (!$url) {
                continue;
            }

            $istornada = (
                stripos($url->name, 'tornada') !== false ||
                stripos($url->name, 'retorn')  !== false
            );

            if (!$istornada) {
                continue;
            }

            if (!$completion->is_enabled($tornadacm)) {
                continue;
            }

            $completiondata = $completion->get_data($tornadacm, false, $userid);
            $completiondata->completionstate = COMPLETION_INCOMPLETE;
            $completiondata->timemodified    = time();
            $completion->internal_set_data($tornadacm, $completiondata, true);

            mtrace("Tornada completion reset for user {$userid} in course {$courseid}.");
            break;
        }
    }
}
