const WORD = /\p{L}+(?:['’]\p{L}+)*/gu;

const SKIP_TAGS = new Set(['SCRIPT', 'STYLE', 'NOSCRIPT', 'TEXTAREA', 'INPUT', 'SELECT', 'OPTION', 'CODE', 'PRE', 'KBD', 'SAMP']);

/**
 * Run every word in a string through a transform, leaving spacing and punctuation alone.
 * @param {string} text
 * @param {(word: string) => string} transformWord
 * @returns {string}
 */
export function rewriteWords(text, transformWord) {
    return text.replace(WORD, (word) => transformWord(word));
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
 * Rewrites the page's words through a transform and puts them back on stop.
 * Vue owns these nodes, so a value it writes over ours becomes the new original.
 * @param {HTMLElement} root
 * @param {(word: string) => string} transformWord
 * @returns {{start: () => void, stop: () => void}}
 */
export function createTextMode(root, transformWord) {
    /** @type {WeakMap<Text, {original: string, converted: string}>} */
    let rewritten = new WeakMap();
    let observer = null;

    function convert(node) {
        const seen = rewritten.get(node);

        if ((seen && node.nodeValue === seen.converted) || !isRewritable(node)) {
            return;
        }

        const converted = rewriteWords(node.nodeValue, transformWord);

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
