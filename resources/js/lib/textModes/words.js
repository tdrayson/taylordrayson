/**
 * Carry the capitals of a source word onto its replacement: HELLO gives AHOY, Hello gives Ahoy.
 * @param {string} source
 * @param {string} replacement
 * @returns {string}
 */
export function matchCase(source, replacement) {
    if (source.length > 1 && source === source.toUpperCase()) {
        return replacement.toUpperCase();
    }

    const first = source[0];

    return first !== first.toLowerCase() ? replacement[0].toUpperCase() + replacement.slice(1) : replacement;
}

/**
 * A word's dictionary key: lower case, curly apostrophes straightened.
 * @param {string} word
 * @returns {string}
 */
export function keyOf(word) {
    return word.toLowerCase().replaceAll('’', "'");
}
