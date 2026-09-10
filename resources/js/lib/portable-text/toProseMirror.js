/**
 * Portable Text -> ProseMirror document. The dialect is whatever
 * PortableTextBlocks.js renders, so read the two together.
 *
 * Every `_key` is carried through as a node attribute, so a document survives the
 * round trip rather than being reassigned new keys and making every save look
 * like a rewrite in the diff.
 */

/** Decorator marks that map straight onto a ProseMirror mark of the same meaning. */
const DECORATORS = { strong: 'bold', em: 'italic', code: 'code', underline: 'underline', 'strike-through': 'strike' };

/**
 * Convert one Portable Text span into a ProseMirror text node, turning its
 * marks into PM marks and resolving link references against the block's
 * markDefs.
 */
function spanToText(span, markDefs) {
    const marks = [];

    for (const mark of span.marks ?? []) {
        if (DECORATORS[mark]) {
            marks.push({ type: DECORATORS[mark] });
            continue;
        }

        const def = (markDefs ?? []).find((candidate) => candidate._key === mark);

        if (def?._type === 'link') {
            marks.push({
                type: 'link',
                attrs: {
                    href: def.href,
                    _key: def._key,
                    target: def.blank === undefined ? null : (def.blank ? '_blank' : '_self'),
                },
            });
        } else if (def?._type === 'dynamicHref') {
            // No href: the tag resolves to one at render, and never before.
            marks.push({
                type: 'link',
                attrs: { _key: def._key, tag: def.tag, options: def.options ?? {} },
            });
        }
    }

    const node = { type: 'text', text: span.text ?? '' };

    if (marks.length) {
        node.marks = marks;
    }

    return node;
}

/**
 * The inline content of a block.
 *
 * Mentions are dropped rather than converted: picking an entry now inserts an
 * ordinary link, and the schema no longer has a mention node to emit one as.
 * A dynamicTag becomes its own node, an atom carrying just the tag name and
 * its options; the value it resolves to is never part of the document.
 */
function inlineContent(block) {
    return (block.children ?? [])
        .filter((child) => child._type !== 'mention' && (child._type === 'dynamicTag' || (child.text ?? '') !== ''))
        .map((child) => (child._type === 'dynamicTag'
            ? { type: 'dynamicTag', attrs: { tag: child.tag, options: child.options ?? {}, _key: child._key } }
            : spanToText(child, block.markDefs)));
}

/** A paragraph, heading or blockquote, i.e. any block that is not a list item. */
function blockToNode(block) {
    const content = inlineContent(block);
    const attrs = { _key: block._key };

    if (/^h[2-6]$/.test(block.style ?? '')) {
        return { type: 'heading', attrs: { ...attrs, level: Number(block.style.slice(1)) }, content };
    }

    if (block.style === 'blockquote') {
        return { type: 'blockquote', attrs, content: [{ type: 'paragraph', content }] };
    }

    return { type: 'paragraph', attrs, content };
}

/**
 * Turn a run of consecutive list-item blocks into nested PM list nodes.
 *
 * Portable Text keeps list items flat, each carrying its own `level`, while
 * ProseMirror nests them. A deeper level opens a child list inside the previous
 * item; a shallower one closes back out.
 *
 * @returns {{node: object, nextIndex: number}}
 */
function listToNode(blocks, startIndex, level, listItem) {
    const items = [];
    let index = startIndex;

    while (index < blocks.length) {
        const block = blocks[index];
        const blockLevel = block.level ?? 1;

        if (block.listItem !== listItem || blockLevel < level) {
            break;
        }

        if (blockLevel > level) {
            // Deeper: nest inside the item just added.
            const nested = listToNode(blocks, index, blockLevel, block.listItem);
            items[items.length - 1].content.push(nested.node);
            index = nested.nextIndex;
            continue;
        }

        items.push({
            type: 'listItem',
            attrs: { _key: block._key },
            content: [{ type: 'paragraph', content: inlineContent(block) }],
        });
        index++;
    }

    return {
        node: {
            type: listItem === 'number' ? 'orderedList' : 'bulletList',
            content: items,
        },
        nextIndex: index,
    };
}

/** The standalone (non-block) node types, each a leaf carrying its own attrs. */
function customToNode(node) {
    switch (node._type) {
        case 'image':
            return {
                type: 'image',
                attrs: {
                    _key: node._key,
                    url: node.url ?? null,
                    alt: node.alt ?? null,
                    ratio: node.ratio ?? null,
                    caption: node.caption ?? null,
                    width: node.width ?? null,
                    height: node.height ?? null,
                },
            };
        case 'video':
            return {
                type: 'video',
                attrs: {
                    _key: node._key,
                    url: node.url ?? null,
                    caption: node.caption ?? null,
                    poster: node.poster ?? null,
                    width: node.width ?? null,
                    height: node.height ?? null,
                },
            };
        case 'code':
            return {
                type: 'codeBlock',
                attrs: {
                    _key: node._key,
                    language: node.language ?? null,
                    filename: node.filename ?? null,
                    lineNumbers: node.lineNumbers ?? null,
                },
                content: (node.code ?? '') === '' ? [] : [{ type: 'text', text: node.code }],
            };
        case 'divider':
            return { type: 'horizontalRule', attrs: { _key: node._key } };
        case 'callout': {
            const content = toProseMirror(node.children ?? []).content;

            return {
                type: 'callout',
                attrs: { _key: node._key, variant: node.variant ?? 'note' },
                // `block+`: an empty callout is rejected outright, and one is
                // exactly what inserting a fresh callout produces.
                content: content.length ? content : [{ type: 'paragraph' }],
            };
        }
        default:
            return null;
    }
}

/**
 * @param {Array<object>} portableText
 * @returns {{type: 'doc', content: Array<object>}}
 */
export function toProseMirror(portableText) {
    const blocks = Array.isArray(portableText) ? portableText : [];
    const content = [];
    let index = 0;

    while (index < blocks.length) {
        const block = blocks[index];

        if (block?._type === 'block' && block.listItem) {
            const { node, nextIndex } = listToNode(blocks, index, block.level ?? 1, block.listItem);
            content.push(node);
            index = nextIndex;
            continue;
        }

        if (block?._type === 'block') {
            content.push(blockToNode(block));
            index++;
            continue;
        }

        const custom = customToNode(block ?? {});

        // An unrecognised node is dropped rather than guessed at, so a bad
        // conversion loses it loudly in the round-trip tests instead of
        // corrupting it quietly.
        if (custom) {
            content.push(custom);
        }

        index++;
    }

    // ProseMirror needs at least one block: a doc with empty content has no
    // paragraph for the caret to sit in and nothing for the placeholder to
    // decorate, which is how every new entry opened blank and unlabelled.
    return { type: 'doc', content: content.length ? content : [{ type: 'paragraph' }] };
}
