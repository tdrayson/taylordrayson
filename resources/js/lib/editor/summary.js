import { clock } from '../format.js';
import { formatDate } from '../dateFormat.js';
import { plainTextOf } from './defaults.js';
import { wallClockParts } from './wallClock.js';

/**
 * A wall clock the way the editor shows it, e.g. "Mon 21 Sep, 12:57pm", with
 * the year only when it is not this one. The zone is its own field, so it is not repeated.
 *
 * @param {{date: string, time: string}} parts From wallClockParts().
 * @param {Date} now What "this year" is measured against.
 * @returns {string}
 */
export function readableWallClock({ date, time }, now = new Date()) {
    const year = date.slice(0, 4) !== String(now.getFullYear());

    return `${formatDate(date, { year })}, ${clock(`${date}T${time}`)}`;
}

/**
 * One field's value on a line, or '' when it is unset: tags joined with commas,
 * a select by its option label, a date in the editor's own format. A date the
 * server stamps at save reads "Now", as DateTimeField shows it.
 *
 * @param {object} field One serialised FieldData.
 * @param {*} value The field's current value on the form.
 * @returns {string}
 */
export function summariseValue(field, value) {
    if (value === null || value === undefined || value === '') {
        return field.defaultsToNow ? 'Now' : '';
    }

    switch (field.type) {
        case 'tags':
            return Array.isArray(value) ? value.join(', ') : '';
        case 'datetime': {
            const parts = wallClockParts(value);

            return parts.date ? readableWallClock(parts) : '';
        }
        case 'select':
            return (field.options ?? []).find((option) => String(option.value) === String(value))?.label ?? String(value);
        case 'boolean':
            return value ? 'Yes' : 'No';
        case 'image':
        case 'gallery':
        case 'book-cover':
            if (! Array.isArray(value) || ! value.length) {
                return '';
            }

            return value.length === 1 ? '1 image' : `${value.length} images`;
        case 'rich-text':
        case 'prose':
            return plainTextOf(value);
        default:
            return `${field.prefix ?? ''}${value}${field.suffix ?? ''}`;
    }
}

/**
 * Several fields' set values on one line, e.g. a collapsed address.
 *
 * @param {Array<object>} fields
 * @param {object} form The editor's form, read for values.
 * @returns {string}
 */
export function summariseFields(fields, form) {
    return fields
        .map((field) => summariseValue(field, form[field.name]))
        .filter(Boolean)
        .join(', ');
}
