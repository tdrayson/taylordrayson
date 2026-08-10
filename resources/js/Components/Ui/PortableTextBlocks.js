import { h } from 'vue';
import CodeBlock from './CodeBlock.vue';
import HeadingAnchor from './HeadingAnchor.vue';
import Icon from './Icon.vue';
import ZoomButton from './ZoomButton.vue';

// Callout tint per variant (GitHub-alert set). Hues borrow the closest timeline
// data-type tokens, the palette having no success/warning/danger scale of its
// own. Chip text stays neutral-900/accent-700: the hue tokens are single values
// with no dark shade to guarantee contrast.
const CALLOUT_VARIANTS = {
    note: { label: 'Note', panel: 'bg-neutral-25', chip: 'bg-neutral-900 text-neutral-0' },
    tip: { label: 'Tip', panel: 'bg-activity/10', chip: 'bg-activity text-neutral-0' },
    important: { label: 'Important', panel: 'bg-accent-50', chip: 'bg-accent-500 text-neutral-0' },
    warning: { label: 'Warning', panel: 'bg-fuel/10', chip: 'bg-fuel text-neutral-900' },
    caution: { label: 'Caution', panel: 'bg-media/10', chip: 'bg-media text-neutral-0' },
};

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

// True for any heading style the dialect supports (h2-h6; h1 is the page title).
function isHeading(style) {
    return /^h[2-6]$/.test(style ?? '');
}

// Walk the whole document once up front so every heading gets a stable,
// deduped anchor id keyed by the block's own _key (repeats within one document
// get -2/-3 suffixes), independent of render order or list grouping below.
function assignHeadingIds(nodes) {
    const seen = new Map();
    const ids = new Map();

    for (const node of nodes) {
        if (node._type !== 'block' || !isHeading(node.style)) {
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
                // Match the site's external-link convention (see ExternalLink.vue):
                // absolute URLs open in a new tab with the arrow icon and the
                // ", opens in a new tab" screen-reader suffix, in prose typography.
                const external = def.href.startsWith('http');

                node = external
                    ? h('a', { href: def.href, rel: 'noopener noreferrer', target: '_blank' }, [
                        node,
                        h('span', { class: 'sr-only' }, ', opens in a new tab'),
                        h(Icon, { icon: 'ArrowUpRight01Icon', class: 'mb-0.5 ml-0.5 inline size-3.5 align-middle' }),
                    ])
                    : h('a', { href: def.href }, node);
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
        classes.push('max-w-prose');
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

// Type scale per heading level: a clean 30/24/20/18/16px ladder so every
// level sits clearly above body text (15px).
const HEADING_CLASSES = {
    h2: 'text-3xl font-bold',
    h3: 'text-2xl font-semibold',
    h4: 'text-xl font-semibold',
    h5: 'text-lg font-semibold',
    h6: 'text-base font-semibold',
};

function renderTextBlock(node, headingIds) {
    const key = node._key;
    const children = renderChildren(node);

    if (isHeading(node.style)) {
        const id = headingIds.get(node);
        const label = blockText(node).trim();
        // Only h2/h3 carry data-toc attributes: deeper levels get anchor ids
        // and copy-link buttons but stay out of the table of contents.
        const inToc = node.style === 'h2' || node.style === 'h3';

        // group/heading lets the trailing HeadingAnchor button reveal itself
        // on hover anywhere over the heading (it also reveals on its own focus).
        return h(node.style, {
            key,
            id,
            class: `group/heading ${HEADING_CLASSES[node.style]} font-display text-neutral-900 max-w-heading scroll-mt-24`,
            'data-toc': inToc ? '' : undefined,
            'data-toc-label': inToc ? label : undefined,
            'data-toc-level': inToc ? node.style.slice(1) : undefined,
        }, [...children, h(HeadingAnchor, { targetId: id, label })]);
    }

    if (node.style === 'blockquote') {
        return h('blockquote', { key, class: 'border-l-2 border-accent-500 py-1 pl-6 font-display text-xl not-italic font-normal leading-relaxed text-neutral-800 max-w-media' }, children);
    }

    return h('p', { key, class: 'max-w-prose' }, children);
}

function renderImage(node, onImageClick) {
    if (!node.url) {
        return null;
    }

    // Stored intrinsic dimensions drive the layout: portraits (and squares)
    // sit ratio-true under the height cap; landscapes fill the media column.
    // Without dimensions we assume landscape, the overwhelmingly common case.
    const portrait = Boolean(node.width && node.height && node.height >= node.width);

    return h('figure', { key: node._key, class: 'max-w-media' }, [
        h('button', {
            type: 'button',
            'aria-label': node.caption ? `View image: ${node.caption}` : 'View image full size',
            // not-prose: the figure keeps prose's block rhythm, but the plugin's
            // img margins must not apply inside the zoom button wrapper. For
            // portraits the button shrink-wraps so the zoom overlay anchors to
            // the image corner, not the column edge.
            class: `group/zoom not-prose relative block rounded-lg focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 ${portrait ? '' : 'w-full'}`,
            onClick: () => onImageClick(node.url),
        }, [
            h('img', {
                src: node.url,
                alt: node.caption || '',
                width: node.width || undefined,
                height: node.height || undefined,
                class: portrait
                    ? 'max-h-media w-auto rounded-lg border border-neutral-50'
                    : 'max-h-media w-full rounded-lg border border-neutral-50 object-cover',
            }),
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

function renderCallout(node) {
    const variant = CALLOUT_VARIANTS[node.variant] ?? CALLOUT_VARIANTS.note;

    // not-prose so the paragraph rhythm cannot leak into a self-contained panel.
    // The outer pt-3 reserves headroom for the label, which sits half above the
    // panel on an absolute -top.
    return h('div', { key: node._key, class: 'not-prose my-8 max-w-media pt-3' }, [
        h('div', { class: `relative rounded-2xl px-6 pb-5 pt-7 ${variant.panel}` }, [
            h('span', {
                class: `absolute -top-3 left-6 inline-block -rotate-2 rounded-md px-3 py-1 font-display text-xs font-bold uppercase tracking-widest shadow-card ${variant.chip}`,
            }, variant.label),
            h('p', { class: 'text-body leading-relaxed text-neutral-800' }, renderChildren(node)),
        ]),
    ]);
}

function renderVideo(node) {
    if (!node.url) {
        return null;
    }

    return h('figure', { key: node._key, class: 'max-w-media' }, [
        h('video', {
            src: node.url,
            controls: true,
            preload: 'metadata',
            width: node.width || undefined,
            height: node.height || undefined,
            // block: replaced elements are inline by default, which leaves a
            // stray gap below them in a grid/flex ancestor; block avoids that
            // the same way the image's button wrapper does for <img>.
            class: 'block max-h-media w-full rounded-lg border border-neutral-50',
        }),
        node.caption
            ? h('figcaption', { class: 'mt-2 text-left text-meta text-neutral-500' }, node.caption)
            : null,
    ]);
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

    if (node._type === 'callout') {
        return renderCallout(node);
    }

    if (node._type === 'video') {
        return renderVideo(node);
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
