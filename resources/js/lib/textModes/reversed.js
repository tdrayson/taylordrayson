/**
 * Write a word backwards, keeping its capitals where they were: Hello becomes Olleh.
 * @param {string} word
 * @returns {string}
 */
export function toReversed(word) {
    const source = [...word];

    return source
        .toReversed()
        .map((char, index) => (source[index] !== source[index].toLowerCase() ? char.toUpperCase() : char.toLowerCase()))
        .join('');
}
