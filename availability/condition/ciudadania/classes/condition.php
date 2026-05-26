<?php
namespace availability_ciudadania;

defined('MOODLE_INTERNAL') || die();

/**
 * Availability condition: checks whether the user has ever completed a payment
 * for this course (i.e. has at least one record in ciudadania_certifications).
 *
 * Used to:
 *   - Grant access to the official certificate after payment.
 *   - Block access to activities that require a prior payment.
 */
class condition extends \core_availability\condition {

    public function __construct($structure) {
        // No configurable options.
    }

    public function save() {
        return (object)['type' => 'ciudadania'];
    }

    /**
     * Returns true if the user meets this condition.
     *
     * @param bool $not True if condition is inverted ("has NOT paid").
     */
    public function is_available($not, \core_availability\info $info, $grabthelot, $userid) {
        global $DB;

        $courseid = $info->get_course()->id;
        $haspaid  = $DB->record_exists('ciudadania_certifications', [
            'userid'   => $userid,
            'courseid' => $courseid,
        ]);

        return $not ? !$haspaid : $haspaid;
    }

    public function get_description($full, $not, \core_availability\info $info) {
        return get_string($not ? 'requires_notpaid' : 'requires_paid', 'availability_ciudadania');
    }

    public function get_debug_string() {
        return 'ciudadania_payment';
    }
}
