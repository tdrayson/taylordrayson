/** Words shorter than this gain nothing: four letters is the first that shrinks (X2Y). */
const MIN_LENGTH = 4;

/**
 * Shorten one word to its numeronym: accessibility becomes a11y.
 * @param {string} word
 * @returns {string}
 */
export function toNumeronym(word) {
    const letters = [...word.replace(/[^\p{L}]/gu, '')];

    if (letters.length < MIN_LENGTH) {
        return word;
    }

    return `${letters[0]}${letters.length - 2}${letters.at(-1)}`;
}
