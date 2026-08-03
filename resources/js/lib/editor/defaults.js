/**
 * The starting value for a field with nothing in it yet.
 *
 * Lives here rather than in each page that builds a form, so /new, an entry
 * page and a content page cannot drift into disagreeing about what an empty
 * field is.
 */
export function defaultValueFor(field) {
    switch (field.type) {
        case 'rich-text':
        case 'tags':
            return [];
        case 'boolean':
            return false;
        // A select bound to '' matches no option and renders blank, which reads
        // as broken rather than as unset. The first option is the sane default.
        case 'select':
            return field.options?.[0]?.value ?? '';
        default:
            return '';
    }
}

/**
 * Values for a whole form, taking what the record already has and falling back
 * to the empty value for its type. Dotted names address into nested data.
 */
export function valuesFor(fields, record = {}) {
    return Object.fromEntries(fields.map((field) => {
        const existing = field.name.split('.').reduce((carry, key) => carry?.[key], record);

        return [field.name, existing ?? defaultValueFor(field)];
    }));
}
