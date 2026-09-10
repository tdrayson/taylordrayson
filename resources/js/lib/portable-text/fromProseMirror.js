/**
 * ProseMirror document -> Portable Text, the inverse of toProseMirror.js. Keys
 * carried as node attributes are restored; anything the editor created fresh gets
 * a new one here.
 */

/** ProseMirror mark -> Portable Text decorator. Link is handled separately. */
const DECORATORS = { bold: 'strong', italic: 'em', code: 'code', underline: 'underline', strike: 'strike-through' };

/** Decorator order is normalised so the same document always serialises identically. */
const DECORATOR_ORDER = ['strong', 'em', 'underline', 'strike-through', 'code'];

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
        // A tag is a reference, not decorated text: only its name and options are
        // stored, and its value is resolved server-side at render.
        if (node.type === 'dynamicTag') {
            children.push({
                _type: 'dynamicTag',
                _key: node.attrs?._key ?? newKey(),
                tag: node.attrs?.tag ?? null,
                options: node.attrs?.options ?? {},
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
                    const def = { _key: key, _type: 'link', href: mark.attrs?.href ?? '' };

                    // Written only once the author has actually chosen, so a
                    // link left alone keeps deciding by its host.
                    if (mark.attrs?.target === '_blank') {
                        def.blank = true;
                    } else if (mark.attrs?.target === '_self') {
                        def.blank = false;
                    }

                    markDefs.push(def);
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

/**
 * A plain paragraph carrying nothing is not worth storing: it is what an
 * untouched editor produces, and saving it would give every new entry a phantom
 * empty paragraph.
 *
 * A styled or listed block is different. An empty quote or heading is one the
 * author has just inserted and is about to type into, and dropping it here
 * deletes it from under them on the very next round trip.
 */
function isDiscardable(block) {
    return block._type === 'block'
        && (block.style ?? 'normal') === 'normal'
        && block.listItem === undefined
        && (block.children ?? []).length === 0;
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
                // Each type keeps only its own extras: an image has alt and a
                // crop ratio, a video the still shown before it plays.
                ...(node.type === 'image'
                    ? { alt: node.attrs?.alt ?? null, ratio: node.attrs?.ratio ?? null }
                    : { poster: node.attrs?.poster ?? null }),
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

    return out.filter((block) => ! isDiscardable(block));
}
