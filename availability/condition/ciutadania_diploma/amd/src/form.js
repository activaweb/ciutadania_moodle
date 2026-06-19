/**
 * Availability condition: ciutadania diploma eligibility.
 *
 * No configurable options — checks in real-time whether the student
 * currently meets all diploma conditions.
 */
define(['jquery'], function($) {

    return {
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
