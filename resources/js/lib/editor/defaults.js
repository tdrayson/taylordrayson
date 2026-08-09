/**
 * The starting value for a field with nothing in it yet.
 *
 * Lives here rather than in each page that builds a form, so /new, an entry
 * page and a content page cannot drift into disagreeing about what an empty
 * field is.
 */

const pad = (n) => String(n).padStart(2, '0');

/** Now as wall-clock text, the same shape the server stores. */
export function nowStamp() {
    const d = new Date();

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

export function defaultValueFor(field) {
    // A field that says it defaults to now should open showing now, not empty
    // with a promise underneath it.
    if (field.defaultsToNow) {
        return nowStamp();
    }

    // Where you are is a better guess than where you live, and it is the whole
    // reason the zone is stored per entry rather than once in config.
    if (field.source === 'timezone') {
        return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
    }

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

/** The slug a title would produce, matching Str::slug on the server. */
export function slugify(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
