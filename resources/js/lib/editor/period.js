const FOUR_DIGIT_YEAR = /^\d{4}$/;

/**
 * Whether a period value is a bare four-digit year, the shape `entries.count`
 * accepts alongside its named presets (`Support\Period::from()`). Checked
 * client-side so a malformed year is caught before the request reaches the
 * server's own regex.
 *
 * @param {string} value
 * @returns {boolean}
 */
export function isFourDigitYear(value) {
    return FOUR_DIGIT_YEAR.test(value ?? '');
}
