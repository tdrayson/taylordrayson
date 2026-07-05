import { h } from 'vue';
import CodeBlock from './CodeBlock.vue';
import ZoomButton from './ZoomButton.vue';

// Turn heading text into a URL-safe slug: lowercase, non-alphanumerics
// collapsed to single hyphens, leading/trailing hyphens trimmed.
function slugify(text) {
    return text
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

// Flatten a block's spans into plain text, ignoring marks; used for heading
// slugs/labels and rendered directly (never as HTML).
function blockText(node) {
    return (node.children ?? []).map((child) => child.text ?? '').join('');
}

// Walk the whole document once up front so every h2/h3 gets a stable, deduped
// anchor id keyed by the block's own _key (repeats within one document get
// -2/-3 suffixes), independent of render order or list grouping below.
function assignHeadingIds(nodes) {
    const seen = new Map();
    const ids = new Map();

    for (const node of nodes) {
        if (node._type !== 'block' || (node.style !== 'h2' && node.style !== 'h3')) {
            continue;
        }

        const base = slugify(blockText(node)) || 'section';
        const count = (seen.get(base) ?? 0) + 1;
        seen.set(base, count);
        // Keyed by node identity so headings missing a _key still get unique ids.
        ids.set(node, count === 1 ? base : `${base}-${count}`);
    }

    return ids;
}

// Render one span, nesting its marks around the text node: decorators
// (strong/em/code) map directly to tags; any other mark key is a markDef
// reference, currently only 'link' is understood.
function renderSpan(span, markDefs) {
    let node = span.text;

    for (const mark of span.marks ?? []) {
        if (mark === 'strong') {
            node = h('strong', node);
        } else if (mark === 'em') {
            node = h('em', node);
        } else if (mark === 'code') {
            node = h('code', node);
        } else {
            const def = (markDefs ?? []).find((markDef) => markDef._key === mark);

            if (def?._type === 'link' && def.href) {
                // Match the site's external-link convention (see SocialLinks.vue):
                // only absolute URLs open in a new tab.
                const external = def.href.startsWith('http');

                node = h('a', { href: def.href, rel: 'noopener', target: external ? '_blank' : undefined }, node);
            }
        }
    }

    return node;
}

function renderChildren(block) {
    return (block.children ?? []).map((span) => renderSpan(span, block.markDefs));
}

// Parse a flat run of consecutive listItem blocks into a nested <ul>/<ol>
// tree: siblings share level+listItem; a jump to a higher level nests inside
// the previous <li>. Returns where the run stopped so a sibling list starting
// at the same level (different listItem type, e.g. bullet then number) can
// be parsed as a separate list by the caller.
function buildListTree(items, startIndex, level, listItem, isTop) {
    const children = [];
    let i = startIndex;

    while (i < items.length && (items[i].level ?? 1) === level && items[i].listItem === listItem) {
        const node = items[i];
        i += 1;

        const liContent = [h('span', renderChildren(node))];

        if (i < items.length && (items[i].level ?? 1) > level) {
            const nested = buildListTree(items, i, items[i].level, items[i].listItem, false);
            liContent.push(nested.vnode);
            i = nested.nextIndex;
        }

        children.push(h('li', { key: node._key }, liContent));
    }

    const tag = listItem === 'number' ? 'ol' : 'ul';
    const classes = [listItem === 'number' ? 'list-decimal' : 'list-disc', 'space-y-1.5', 'pl-5'];

    if (isTop) {
        classes.push('max-w-reading');
    }

    return { vnode: h(tag, { class: classes.join(' ') }, children), nextIndex: i };
}

// A run may contain more than one top-level list (e.g. a bullet list directly
// followed by a numbered list at the same level); keep parsing fresh lists
// until the whole run is consumed.
function renderListRun(run) {
    const vnodes = [];
    let index = 0;

    while (index < run.length) {
        const level = run[index].level ?? 1;
        const listItem = run[index].listItem;
        const { vnode, nextIndex } = buildListTree(run, index, level, listItem, true);

        vnodes.push(vnode);
        index = nextIndex;
    }

    return vnodes;
}

function renderTextBlock(node, headingIds) {
    const key = node._key;
    const children = renderChildren(node);

    if (node.style === 'h2' || node.style === 'h3') {
        return h(node.style, {
            key,
            id: headingIds.get(node),
            class:
                node.style === 'h2'
                    ? 'font-display text-item-title text-neutral-900 max-w-reading scroll-mt-24'
                    : 'text-lg font-semibold font-display text-neutral-900 max-w-reading scroll-mt-24',
            'data-toc': '',
            'data-toc-label': blockText(node).trim(),
            'data-toc-level': node.style === 'h2' ? '2' : '3',
        }, children);
    }

    if (node.style === 'blockquote') {
        return h('blockquote', { key, class: 'border-l-2 border-accent-500 py-1 pl-6 font-display text-xl leading-relaxed text-neutral-800 max-w-media' }, children);
    }

    return h('p', { key, class: 'max-w-reading' }, children);
}

function renderImage(node, onImageClick) {
    if (!node.url) {
        return null;
    }

    return h('figure', { key: node._key, class: 'max-w-media' }, [
        h('button', {
            type: 'button',
            'aria-label': node.caption ? `View image: ${node.caption}` : 'View image full size',
            class: 'group/zoom relative block w-full rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2',
            onClick: () => onImageClick(node.url),
        }, [
            h('img', { src: node.url, alt: node.caption || '', class: 'w-full rounded-lg border border-neutral-50' }),
            h('span', {
                class: 'pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-visible/zoom:opacity-100',
            }, [h(ZoomButton)]),
        ]),
        node.caption
            ? h('figcaption', { class: 'mt-2 text-left text-meta text-neutral-500' }, node.caption)
            : null,
    ]);
}

function renderCode(node) {
    return h(CodeBlock, {
        key: node._key,
        code: node.code ?? '',
        language: node.language ?? null,
        filename: node.filename ?? null,
        lineNumbers: node.lineNumbers ?? false,
    });
}

function renderNode(node, headingIds, onImageClick) {
    if (node._type === 'block') {
        return renderTextBlock(node, headingIds);
    }

    if (node._type === 'image') {
        return renderImage(node, onImageClick);
    }

    if (node._type === 'code') {
        return renderCode(node);
    }

    if (node._type === 'divider') {
        return h('hr', { key: node._key, class: 'border-neutral-50' });
    }

    return null;
}

// Single pass over the document: consecutive listItem blocks are peeled off
// into their own grouped run (see renderListRun); everything else renders node-by-node.
function renderDocument(nodes, headingIds, onImageClick) {
    const out = [];
    let i = 0;

    while (i < nodes.length) {
        const node = nodes[i];

        if (node._type === 'block' && node.listItem) {
            const run = [];

            while (i < nodes.length && nodes[i]._type === 'block' && nodes[i].listItem) {
                run.push(nodes[i]);
                i += 1;
            }

            out.push(...renderListRun(run));
        } else {
            const vnode = renderNode(node, headingIds, onImageClick);

            if (vnode) {
                out.push(vnode);
            }

            i += 1;
        }
    }

    return out;
}

// Render-function component for a Portable Text node array. Emits
// 'image-click' so the host (BlockContent.vue) can drive a shared lightbox.
export default {
    name: 'PortableTextBlocks',
    props: {
        nodes: { type: Array, default: () => [] },
    },
    emits: ['image-click'],
    setup(props, { emit }) {
        return () => {
            const headingIds = assignHeadingIds(props.nodes);

            return renderDocument(props.nodes, headingIds, (url) => emit('image-click', url));
        };
    },
};
