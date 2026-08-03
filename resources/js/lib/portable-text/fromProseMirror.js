/**
 * ProseMirror document -> Portable Text.
 *
 * The inverse of toProseMirror.js; read the two together. Keys carried through
 * as node attributes are restored, and anything the editor created fresh gets a
 * new key here, since ProseMirror has no notion of one.
 */

/** ProseMirror mark -> Portable Text decorator. Link is handled separately. */
const DECORATORS = { bold: 'strong', italic: 'em', code: 'code' };

/** Decorator order is normalised so the same document always serialises identically. */
const DECORATOR_ORDER = ['strong', 'em', 'code'];

let keyCounter = 0;

/**
 * A key for a node the editor created. Deterministic within a conversion so
 * tests are stable; real documents keep the keys they arrived with.
 */
function newKey() {
    keyCounter += 1;

    return `k${keyCounter}`;
}

export function resetKeyCounter() {
    keyCounter = 0;
}

/**
 * Convert a run of PM text nodes into Portable Text spans plus the markDefs
 * their links reference.
 *
 * @returns {{children: Array<object>, markDefs: Array<object>}}
 */
function inlineToSpans(content) {
    const children = [];
    const markDefs = [];

    for (const node of content ?? []) {
        // A mention is a reference, not decorated text: it stores kind and id
        // only, and its title is resolved at render time.
        if (node.type === 'mention') {
            children.push({
                _type: 'mention',
                _key: node.attrs?._key ?? newKey(),
                kind: node.attrs?.kind ?? null,
                id: node.attrs?.id ?? null,
            });
            continue;
        }

        if (node.type !== 'text') {
            continue;
        }

        const decorators = [];
        const linkKeys = [];

        for (const mark of node.marks ?? []) {
            if (DECORATORS[mark.type]) {
                decorators.push(DECORATORS[mark.type]);
                continue;
            }

            if (mark.type === 'link') {
                const key = mark.attrs?._key ?? newKey();

                if (! markDefs.some((def) => def._key === key)) {
                    markDefs.push({ _key: key, _type: 'link', href: mark.attrs?.href ?? '' });
                }

                linkKeys.push(key);
            }
        }

        decorators.sort((a, b) => DECORATOR_ORDER.indexOf(a) - DECORATOR_ORDER.indexOf(b));

        const span = { _type: 'span', _key: newKey(), text: node.text ?? '', marks: [...decorators, ...linkKeys] };

        children.push(span);
    }

    return { children, markDefs };
}

/** Build a Portable Text block, omitting markDefs when the block has no links. */
function block(key, style, content, extra = {}) {
    const { children, markDefs } = inlineToSpans(content);
    const result = { _type: 'block', _key: key ?? newKey(), style, children, ...extra };

    if (markDefs.length) {
        result.markDefs = markDefs;
    }

    return result;
}

/**
 * Flatten a PM list back into consecutive Portable Text blocks, each carrying
 * its own level. Nested lists recurse one level deeper.
 */
function listToBlocks(node, level, out) {
    const listItem = node.type === 'orderedList' ? 'number' : 'bullet';

    for (const item of node.content ?? []) {
        const paragraph = (item.content ?? []).find((child) => child.type === 'paragraph');

        out.push(block(item.attrs?._key, 'normal', paragraph?.content, { listItem, level }));

        for (const child of item.content ?? []) {
            if (child.type === 'bulletList' || child.type === 'orderedList') {
                listToBlocks(child, level + 1, out);
            }
        }
    }
}

/** The standalone node types, mirrored from toProseMirror's customToNode. */
function customToBlock(node) {
    const key = node.attrs?._key ?? newKey();

    switch (node.type) {
        case 'image':
        case 'video':
            return {
                _type: node.type,
                _key: key,
                url: node.attrs?.url ?? null,
                caption: node.attrs?.caption ?? null,
                width: node.attrs?.width ?? null,
                height: node.attrs?.height ?? null,
            };
        case 'codeBlock':
            return {
                _type: 'code',
                _key: key,
                code: (node.content ?? []).map((child) => child.text ?? '').join(''),
                language: node.attrs?.language ?? null,
                filename: node.attrs?.filename ?? null,
                lineNumbers: node.attrs?.lineNumbers ?? null,
            };
        case 'horizontalRule':
            return { _type: 'divider', _key: key };
        case 'callout':
            return {
                _type: 'callout',
                _key: key,
                variant: node.attrs?.variant ?? 'note',
                children: fromProseMirror({ type: 'doc', content: node.content ?? [] }),
            };
        default:
            return null;
    }
}

/**
 * @param {{type: 'doc', content: Array<object>}} doc
 * @returns {Array<object>}
 */
export function fromProseMirror(doc) {
    const out = [];

    for (const node of doc?.content ?? []) {
        if (node.type === 'paragraph') {
            out.push(block(node.attrs?._key, 'normal', node.content));
            continue;
        }

        if (node.type === 'heading') {
            out.push(block(node.attrs?._key, `h${node.attrs?.level ?? 2}`, node.content));
            continue;
        }

        if (node.type === 'blockquote') {
            const paragraph = (node.content ?? []).find((child) => child.type === 'paragraph');
            out.push(block(node.attrs?._key, 'blockquote', paragraph?.content));
            continue;
        }

        if (node.type === 'bulletList' || node.type === 'orderedList') {
            listToBlocks(node, 1, out);
            continue;
        }

        const custom = customToBlock(node);

        if (custom) {
            out.push(custom);
        }
    }

    return out;
}
