/**
 * Whether a link's text is just its address, i.e. a pasted URL rather than words
 * the author chose.
 *
 * @param {string} text The link text.
 * @param {string} href The link's address.
 * @returns {boolean}
 */
export function isBareUrl(text, href) {
    const strip = (value) => value.replace(/\/$/, '').replace(/^https?:\/\//, '');

    return typeof text === 'string' && typeof href === 'string' && href !== ''
        && strip(text.trim()) === strip(href.trim());
}
