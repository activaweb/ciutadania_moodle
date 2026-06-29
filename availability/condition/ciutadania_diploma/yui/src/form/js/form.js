/**
 * JavaScript for form editing ciutadania diploma eligibility condition.
 * This condition has no configurable options — it simply shows the title.
 *
 * @module moodle-availability_ciutadania_diploma-form
 */
M.availability_ciutadania_diploma = M.availability_ciutadania_diploma || {};

/**
 * @class M.availability_ciutadania_diploma.form
 * @extends M.core_availability.plugin
 */
M.availability_ciutadania_diploma.form = Y.Object(M.core_availability.plugin);

M.availability_ciutadania_diploma.form.initInner = function() {
    // No initialisation needed — condition has no options.
};

M.availability_ciutadania_diploma.form.getNode = function(json) {
    var html = '<span class="col-form-label pe-3">' +
               M.util.get_string('title', 'availability_ciutadania_diploma') +
               '</span>';
    return Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');
};

M.availability_ciutadania_diploma.form.fillValue = function(value, node) {
    // No values to save.
};

M.availability_ciutadania_diploma.form.fillErrors = function(errors, node) {
    // No validation needed.
};
