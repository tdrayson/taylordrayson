/**
 * A media field is two shapes at once: the editor holds `{ id, name, url }` so
 * it can draw a thumbnail, and the server wants the ordered list of ids that
 * `SyncEntryMedia` reads. These convert between them at the form boundary.
 */

/** The field types whose value is a list of media rather than a scalar. */
const MEDIA_TYPES = ['image', 'gallery'];

/**
 * The ids behind a media field's value, dropping anything without one.
 *
 * Accepts a bare id as well as an item, because an existing attachment and a
 * freshly uploaded one arrive by different routes and only one of them is ever
 * a plain string.
 *
 * @param {Array<string|{id?: string}>|unknown} value
 * @returns {string[]}
 */
export function mediaIds(value) {
    return (Array.isArray(value) ? value : [])
        .map((item) => (typeof item === 'string' ? item : item?.id))
        .filter((id) => typeof id === 'string' && id !== '');
}

/**
 * The form's values with every media field reduced to its ids, ready to post.
 *
 * @param {Array<{name: string, type: string}>} fields
 * @param {Record<string, unknown>} values
 * @returns {Record<string, unknown>}
 */
export function withMediaIds(fields, values) {
    const reduced = { ...values };

    for (const field of fields) {
        if (MEDIA_TYPES.includes(field.type)) {
            reduced[field.name] = mediaIds(reduced[field.name]);
        }
    }

    return reduced;
}
