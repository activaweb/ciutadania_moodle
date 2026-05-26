/**
 * Availability condition: ciudadania payment.
 *
 * This condition has no configurable options — it simply checks whether
 * the user has ever completed a payment in this course.
 */
define(['jquery'], function($) {

    return {
        /**
         * Initialises this condition (no UI options needed).
         */
        init: function(container) {
            // No form elements to render.
        },

        getValue: function(container) {
            return {};
        },

        setValue: function(container, value) {
            // Nothing to restore.
        },

        hasError: function(container) {
            return false;
        }
    };
});
