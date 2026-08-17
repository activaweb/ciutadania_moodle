<?php
namespace availability_ciutadania_diploma;

defined('MOODLE_INTERNAL') || die();

/**
 * Availability condition: checks in real-time whether the user currently meets
 * all diploma conditions (mandatory modules approved + average of eligible >= 70).
 *
 * Used on the payment call URL ("URL de crida") as a second gate, complementing
 * the "tornada not completed" condition. This ensures that even if the tornada
 * was previously reset (unlocked), the user cannot initiate a new payment if
 * their current average has since dropped below 70.
 *
 * Delegates all business logic to snapshot_manager::get_current_approved_modules().
 */
class condition extends \core_availability\condition {

    public function __construct($structure) {
        // No configurable options.
    }

    public function save() {
        return (object)['type' => 'ciutadania_diploma'];
    }

    /**
     * Returns true if the user currently meets all diploma conditions AND has at
     * least one eligible module that is not yet part of their last paid snapshot
     * (i.e. there is something new worth paying for). Without this check, a
     * still-eligible user who has already certified everything they qualify for
     * would keep seeing the payment button, letting them pay again for nothing.
     *
     * @param bool $not True if condition is inverted.
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        $courseid = $info->get_course()->id;

        $eligible = \local_ciudadania_certs\snapshot_manager::get_current_approved_modules(
            $userid,
            $courseid
        );

        $meets = false;
        if (!empty($eligible)) {
            $certified = \local_ciudadania_certs\snapshot_manager::get_certified_modules($userid, $courseid);
            $certifiedcmids = array_column($certified, 'cmid');
            $eligiblecmids = array_column($eligible, 'cmid');
            $meets = !empty(array_diff($eligiblecmids, $certifiedcmids));
        }

        return $not ? !$meets : $meets;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        return get_string(
            $not ? 'requires_noteligible' : 'requires_eligible',
            'availability_ciutadania_diploma'
        );
    }

    public function get_debug_string() {
        return 'ciutadania_diploma_eligible';
    }
}
