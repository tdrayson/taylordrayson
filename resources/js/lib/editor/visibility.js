/**
 * Whether a conditional field should be shown.
 *
 * A field carries `showWhen` as a map of field name to the values that reveal
 * it; an empty list means any value at all. Every entry has to be satisfied, so
 * two conditions read as "and".
 *
 * @param {object} field One serialised FieldData.
 * @param {object} values The form's current values, keyed by field name.
 * @returns {boolean}
 */
export function revealed(field, values) {
    if (! field.showWhen) {
        return true;
    }

    return Object.entries(field.showWhen).every(([name, allowed]) => {
        const value = values[name];

        if (value === null || value === undefined || value === '') {
            return false;
        }

        return allowed.length === 0 || allowed.includes(value);
    });
}

/**
 * The names of the fields whose condition is no longer met.
 *
 * What the editor clears: choosing "RSVP", answering it, then switching to
 * "Like" would otherwise save an answer nobody can see any more, and a stray
 * property is what post type discovery reads a post's whole type from.
 *
 * @param {Array<object>} fields Every serialised FieldData on the entry.
 * @param {object} values The form's current values, keyed by field name.
 * @returns {Array<string>}
 */
export function hiddenNames(fields, values) {
    return fields
        .filter((field) => field.showWhen && ! revealed(field, values))
        .map((field) => field.name);
}
