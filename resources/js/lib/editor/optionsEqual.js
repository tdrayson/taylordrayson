/**
 * Whether two flat option objects hold the same key/value pairs, ignoring key
 * order. Plain `JSON.stringify` equality breaks the moment either side builds
 * its object with keys in a different order, which a hand-built options form
 * does far more often than the server's own default-filling.
 *
 * @param {Object<string, string>} a
 * @param {Object<string, string>} b
 * @returns {boolean}
 */
export function optionsEqual(a, b) {
    const left = a ?? {};
    const right = b ?? {};
    const leftKeys = Object.keys(left);

    if (leftKeys.length !== Object.keys(right).length) {
        return false;
    }

    return leftKeys.every((key) => left[key] === right[key]);
}
