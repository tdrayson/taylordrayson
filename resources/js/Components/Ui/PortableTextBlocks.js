import { h } from 'vue';
import { usePage } from '@inertiajs/vue3';
import CodeBlock from './CodeBlock.vue';
import HeadingAnchor from './HeadingAnchor.vue';
import Icon from './Icon.vue';
import ZoomButton from './ZoomButton.vue';
import { entryType } from '../../entryTypes';
import { CALLOUT_VARIANTS } from '../../lib/editor/callouts';
import VideoEmbed from './VideoEmbed.vue';


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

// The display host for a URL: lowercase, no leading www. Mirrors Links::host()
// on the server so a favicon looked up here matches the one stored there.
function hostOf(href) {
    try {
        return new URL(href).hostname.toLowerCase().replace(/^www\./, '');
    } catch {
        return null;
    }
}

// True when the link text is just the address, i.e. a pasted URL rather than
// words the author chose. Only then may the label be replaced.
function isBareUrl(text, href) {
    const strip = (value) => value.replace(/\/$/, '').replace(/^https?:\/\//, '');

    return typeof text === 'string' && strip(text.trim()) === strip(href);
}

/**
 * This site's own host. The browser reads it off the address bar; the server
 * has no address bar, so the component below stands the app URL in its place.
 * Constant per deployment, which is why holding it at module scope is safe.
 */
let siteHost = null;

/** A destination on another site, as opposed to a path or a URL back to this one. */
function isExternalHref(href) {
    const host = hostOf(href);
    const own = typeof window === 'undefined' ? siteHost : hostOf(window.location.href);

    return host !== null && host !== own;
}

/**
 * A link's children with its icon tied to the first word.
 *
 * An inline image is a line-break opportunity, so a link landing near the end
 * of a line could leave its icon stranded alone on one row with every word of
 * the link on the next. Only the icon and the first word are held together;
 * everything after them still wraps, so a long link is never forced to overflow.
 *
 * @param {object} mark The icon vnode.
 * @param {string|object} label The link text, or a vnode when a mark (bold,
 *   code) already wrapped it, which cannot be split and so rides with the icon.
 * @returns {Array} The anchor's children.
 */
function iconWithLabel(mark, label) {
    const nowrap = (children) => h('span', { class: 'whitespace-nowrap' }, children);

    if (typeof label !== 'string') {
        return [nowrap([mark, label])];
    }

    const space = label.indexOf(' ');

    if (space === -1) {
        return [nowrap([mark, label])];
    }

    return [nowrap([mark, label.slice(0, space)]), label.slice(space)];
}

/**
 * An external link: the site's favicon, then the author's own words. The text is
 * never swapped for a fetched title, or anchor text like "click here" would turn
 * into nonsense. A pasted URL is the one exception, collapsing to the domain
 * rather than sitting in the sentence as a raw address.
 */
function renderExternalLink(def, label, text, favicons) {
    const host = hostOf(def.href);
    const favicon = host ? favicons[host] : null;

    const mark = favicon
        // not-prose: the typography plugin styles every img as a block figure
        // with a 2em margin, which drops an inline favicon onto its own line.
        ? h('img', {
            src: favicon,
            alt: '',
            loading: 'lazy',
            class: 'not-prose mb-0.5 mr-1 inline size-3.5 object-contain align-middle',
        })
        // A globe rather than nothing: without it some external links carry a
        // mark and some do not, which reads as broken rather than deliberate.
        : h(Icon, { icon: 'Globe02Icon', class: 'mb-0.5 mr-1 inline size-3.5 align-middle text-neutral-400' });

    // The author's choice wins where they made one; otherwise an external
    // destination opens away, which is the expected default.
    const away = def.blank ?? true;

    return h('a', {
        href: def.href,
        rel: away ? 'noopener noreferrer' : null,
        target: away ? '_blank' : null,
        'data-external': '',
    }, [
        ...iconWithLabel(mark, isBareUrl(text, def.href) && host ? host : label),
        away ? h('span', { class: 'sr-only' }, ', opens in a new tab') : null,
    ]);
}

/**
 * An internal link that resolves to an entry: a chip carrying that entry type's
 * glyph, so a reference that keeps you on the site reads differently from one
 * that leaves it. A link with no entry behind it (an archive page, an
 * unpublished target) stays an ordinary link.
 *
 * The glyph takes the type's hue and nothing else does. Tinting the fill per
 * type would put a dozen colours through a paragraph and move the text contrast
 * with each one; an accent fill is worse still, since accent blue is itself a
 * hue and fights whichever type colour lands on it.
 */
function renderInternalLink(def, label, text, previews) {
    const preview = previews[def.href];

    if (! preview) {
        return h('a', { href: def.href }, label);
    }

    // A pasted address is not anchor text anyone chose, so the entry names
    // itself rather than sitting in the sentence as a URL. Same exception the
    // external branch makes, which collapses to the host instead.
    const words = isBareUrl(text, def.href) && preview.title ? preview.title : label;

    return h('a', {
        href: def.href,
        class: 'entry-chip box-decoration-clone rounded bg-neutral-25 px-1 py-0.5 font-medium text-neutral-900 no-underline',
    }, iconWithLabel(
        h(Icon, {
            icon: entryType(preview.type).icon,
            class: 'mb-0.5 mr-1 inline size-3.5 align-middle',
            style: preview.accent ? { color: `var(--color-${preview.accent})` } : null,
        }),
        words,
    ));
}

// Render one span, nesting its marks around the text node: decorators
// (strong/em/code) map directly to tags; any other mark key is a markDef
// reference, currently only 'link' is understood.
function renderSpan(span, markDefs, favicons, previews) {
    let node = span.text;

    for (const mark of span.marks ?? []) {
        if (mark === 'strong') {
            node = h('strong', node);
        } else if (mark === 'em') {
            node = h('em', node);
        } else if (mark === 'code') {
            node = h('code', node);
        } else if (mark === 'underline') {
            node = h('u', node);
        } else if (mark === 'strike-through') {
            node = h('s', node);
        } else {
            const def = (markDefs ?? []).find((markDef) => markDef._key === mark);

            if (def?._type === 'link' && def.href) {
                // By host, not by protocol: an absolute URL to this site is
                // still an internal link, and treating it as external would
                // open our own page in a new tab.
                node = isExternalHref(def.href)
                    ? renderExternalLink(def, node, span.text, favicons)
                    : renderInternalLink(def, node, span.text, previews);
            }
        }
    }

    return node;
}

function renderChildren(block, favicons, previews) {
    return (block.children ?? []).map((span) => renderSpan(span, block.markDefs, favicons, previews));
}

// Parse a flat run of consecutive listItem blocks into a nested <ul>/<ol>
// tree: siblings share level+listItem; a jump to a higher level nests inside
// the previous <li>. Returns where the run stopped so a sibling list starting
// at the same level (different listItem type, e.g. bullet then number) can
// be parsed as a separate list by the caller.
function buildListTree(items, startIndex, level, listItem, isTop, favicons, previews) {
    const children = [];
    let i = startIndex;

    while (i < items.length && (items[i].level ?? 1) === level && items[i].listItem === listItem) {
        const node = items[i];
        i += 1;

        const liContent = [h('span', renderChildren(node, favicons, previews))];

        if (i < items.length && (items[i].level ?? 1) > level) {
            const nested = buildListTree(items, i, items[i].level, items[i].listItem, false, favicons, previews);
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
function renderListRun(run, favicons, previews) {
    const vnodes = [];
    let index = 0;

    while (index < run.length) {
        const level = run[index].level ?? 1;
        const listItem = run[index].listItem;
        const { vnode, nextIndex } = buildListTree(run, index, level, listItem, true, favicons, previews);

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

function renderTextBlock(node, headingIds, favicons, previews) {
    const key = node._key;
    const children = renderChildren(node, favicons, previews);

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

function renderCallout(node, favicons, previews) {
    const variant = CALLOUT_VARIANTS[node.variant] ?? CALLOUT_VARIANTS.note;

    // not-prose so the paragraph rhythm cannot leak into a self-contained panel.
    // The outer pt-3 reserves headroom for the label, which sits half above the
    // panel on an absolute -top.
    return h('div', { key: node._key, class: 'not-prose my-8 max-w-media pt-3' }, [
        h('div', {
            class: `callout-panel relative rounded-2xl px-6 pb-5 pt-7 ${variant.panel}`,
            style: { '--callout-code': variant.code },
        }, [
            h('span', {
                class: `absolute -top-3 left-6 inline-block -rotate-2 rounded-md px-3 py-1 font-display text-xs font-bold uppercase tracking-widest shadow-card ${variant.chip}`,
            }, variant.label),
            h('p', { class: 'text-body leading-relaxed text-neutral-800' }, renderChildren(node, favicons, previews)),
        ]),
    ]);
}

function renderVideo(node) {
    if (!node.url) {
        return null;
    }

    // The component owns the placeholder-then-embed behaviour, so nothing
    // off-site loads until the reader presses play.
    return h(VideoEmbed, {
        key: node._key,
        url: node.url,
        caption: node.caption || null,
        poster: node.poster || null,
        width: node.width || null,
        height: node.height || null,
    });
}

function renderNode(node, headingIds, onImageClick, favicons, previews) {
    if (node._type === 'block') {
        return renderTextBlock(node, headingIds, favicons, previews);
    }

    if (node._type === 'image') {
        return renderImage(node, onImageClick);
    }

    if (node._type === 'code') {
        return renderCode(node);
    }

    if (node._type === 'callout') {
        return renderCallout(node, favicons, previews);
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
function renderDocument(nodes, headingIds, onImageClick, favicons, previews) {
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

            out.push(...renderListRun(run, favicons, previews));
        } else {
            const vnode = renderNode(node, headingIds, onImageClick, favicons, previews);

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
        // Map of host -> stored favicon URL, for external link chips.
        favicons: { type: Object, default: () => ({}) },
        // Map of internal href -> preview, so a resolved link renders as a chip.
        previews: { type: Object, default: () => ({}) },
    },
    emits: ['image-click'],
    setup(props, { emit }) {
        siteHost = hostOf(usePage().props.appUrl ?? '');

        return () => {
            const headingIds = assignHeadingIds(props.nodes);

            return renderDocument(props.nodes, headingIds, (url) => emit('image-click', url), props.favicons, props.previews);
        };
    },
};
