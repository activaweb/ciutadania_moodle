/**
 * JavaScript for form editing ciudadania payment condition.
 * This condition has no configurable options — it simply shows the title.
 *
 * @module moodle-availability_ciudadania-form
 */
M.availability_ciudadania = M.availability_ciudadania || {};

/**
 * @class M.availability_ciudadania.form
 * @extends M.core_availability.plugin
 */
M.availability_ciudadania.form = Y.Object(M.core_availability.plugin);

M.availability_ciudadania.form.initInner = function() {
    // No initialisation needed — condition has no options.
};

M.availability_ciudadania.form.getNode = function(json) {
    var html = '<span class="col-form-label pe-3">' +
               M.util.get_string('title', 'availability_ciudadania') +
               '</span>';
    return Y.Node.create('<span class="d-flex flex-wrap align-items-center">' + html + '</span>');
};

M.availability_ciudadania.form.fillValue = function(value, node) {
    // No values to save.
};

M.availability_ciudadania.form.fillErrors = function(errors, node) {
    // No validation needed.
};
