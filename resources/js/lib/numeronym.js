/** Words shorter than this gain nothing: four letters is the first that shrinks (X2Y). */
const MIN_LENGTH = 4;

const WORD = /\p{L}+(?:['’]\p{L}+)*/gu;

const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION', 'CODE', 'PRE', 'KBD', 'SAMP']);

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

/**
 * Shorten every word in a string, leaving spacing and punctuation alone.
 * @param {string} text
 * @returns {string}
 */
export function numeronymise(text) {
    return text.replace(WORD, toNumeronym);
}

/**
 * Whether a text node is page copy we may rewrite. Dialogs are left readable
 * so the settings switch can always be found again.
 * @param {Text} node
 * @returns {boolean}
 */
function isRewritable(node) {
    const parent = node.parentElement;

    if (!parent || !node.nodeValue.trim() || parent.isContentEditable || parent.closest('[role="dialog"]')) {
        return false;
    }

    for (let el = parent; el; el = el.parentElement) {
        if (SKIP_TAGS.has(el.tagName)) {
            return false;
        }
    }

    return true;
}

/**
 * Rewrites the page's text nodes as numeronyms and puts them back on stop.
 * Vue owns these nodes, so a value it writes over ours becomes the new original.
 * @param {HTMLElement} root
 * @returns {{start: () => void, stop: () => void}}
 */
export function createNumeronymMode(root) {
    /** @type {WeakMap<Text, {original: string, converted: string}>} */
    let rewritten = new WeakMap();
    let observer = null;

    function convert(node) {
        const seen = rewritten.get(node);

        if ((seen && node.nodeValue === seen.converted) || !isRewritable(node)) {
            return;
        }

        const converted = numeronymise(node.nodeValue);

        if (converted !== node.nodeValue) {
            rewritten.set(node, { original: node.nodeValue, converted });
            node.nodeValue = converted;
        }
    }

    function convertTree(tree) {
        if (tree.nodeType === Node.TEXT_NODE) {
            convert(tree);

            return;
        }

        const walker = document.createTreeWalker(tree, NodeFilter.SHOW_TEXT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            convert(node);
        }
    }

    function start() {
        if (observer) {
            return;
        }

        convertTree(root);

        observer = new MutationObserver((mutations) => {
            for (const mutation of mutations) {
                if (mutation.type === 'characterData') {
                    convert(mutation.target);
                } else {
                    mutation.addedNodes.forEach(convertTree);
                }
            }
        });

        observer.observe(root, { childList: true, characterData: true, subtree: true });
    }

    function stop() {
        if (!observer) {
            return;
        }

        observer.disconnect();
        observer = null;

        const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT);

        for (let node = walker.nextNode(); node; node = walker.nextNode()) {
            const seen = rewritten.get(node);

            if (seen && node.nodeValue === seen.converted) {
                node.nodeValue = seen.original;
            }
        }

        rewritten = new WeakMap();
    }

    return { start, stop };
}
