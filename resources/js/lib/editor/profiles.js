import StarterKit from '@tiptap/starter-kit';
import TiptapLink from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { PreserveKeys, CodeBlockMeta, ImageMeta, Video, Callout, DynamicTagNode, parseDynamicOptions } from './nodes.js';

/** The display host for a URL, or null for an internal path. */
function hostOf(href) {
    try {
        return new URL(href).hostname.toLowerCase().replace(/^www\./, '');
    } catch {
        return null;
    }
}

/**
 * The link mark drawn as a span, not an anchor.
 *
 * An anchor inside a contenteditable is still an anchor, and a target="_blank"
 * one is followed by the browser on click whatever the editor does about it.
 * There is nowhere in a draft you want to be taken, so the address rides on a
 * data attribute and the toolbar is the only way to reach it.
 */
const Link = TiptapLink.extend({
    /**
     * The caret sitting after a link is outside it, so a space typed there
     * follows the link rather than being swallowed into the linked text and
     * then stripped when autolink re-marks the URL alone.
     *
     * Overridden on the extension rather than passed to configure(): Tiptap
     * derives this from options.autolink, so configure() cannot reach it and
     * the only other way to get it is turning autolink off.
     */
    inclusive() {
        return false;
    },

    /**
     * `_key` keeps this mark's identity across a save, the same as every other
     * node's; a mark carries no attribute TipTap doesn't declare, so without
     * this the key silently drops the moment the document round-trips through
     * the editor. `tag`/`options` let a link point at a dynamic tag instead of
     * a stored `href`.
     */
    addAttributes() {
        return {
            ...this.parent?.(),
            _key: { default: null, rendered: false },
            tag: { default: null, rendered: false },
            options: { default: null, rendered: false },
        };
    },

    /**
     * Anchors, plus this mark's own output.
     *
     * Copying from the editor puts spans on the clipboard, not anchors, so
     * without the second rule pasting the editor's own content back into it
     * drops every link in the selection.
     */
    parseHTML() {
        return [
            { tag: 'a[href]' },
            {
                tag: 'span[data-href]',
                getAttrs: (element) => ({
                    href: element.getAttribute('data-href'),
                    target: element.getAttribute('data-target'),
                    tag: element.getAttribute('data-dynamic-tag'),
                    options: parseDynamicOptions(element.getAttribute('data-dynamic-options'), null),
                }),
            },
        ];
    },

    renderHTML({ mark, HTMLAttributes }) {
        const href = HTMLAttributes.href ?? '';
        // A URL back to this site is internal, however it is written.
        const host = hostOf(href) === hostOf(window.location.href) ? null : hostOf(href);

        return ['span', {
            class: 'editor-link',
            'data-href': href,
            'data-target': HTMLAttributes.target,
            // A dynamic link has no href to compute a favicon from, and needs
            // its tag on the DOM so copy/paste round-trips it rather than
            // silently downgrading to a plain, empty link.
            ...(mark.attrs.tag
                ? { 'data-dynamic-tag': mark.attrs.tag, 'data-dynamic-options': JSON.stringify(mark.attrs.options ?? {}) }
                : {}),
            // An internal link has no host to fetch an icon for, so it reads as
            // the entry chip the published page renders instead.
            ...(host
                // Straight from the icon service while writing; the published
                // page serves the copy stored at save time, so a reader never
                // makes a request to a third party.
                ? { style: `--editor-link-favicon: url(https://www.google.com/s2/favicons?domain=${host}&sz=64)` }
                : { 'data-internal': '' }),
        }, 0];
    },
});

/**
 * What each kind of writing is allowed to contain. `document` spreads `prose`
 * rather than repeating it, since a document is a note with more room.
 */

/** Everything the shorter form allows. */
const PROSE = [
    StarterKit.configure({
        heading: false,
        blockquote: false,
        horizontalRule: false,
        codeBlock: false,
        // Ours renders as a span rather than an anchor; two marks named `link`
        // is a duplicate the schema refuses to build.
        link: false,
    }),
    Link.configure({ openOnClick: false, linkOnPaste: true }),
    DynamicTagNode,
];

/**
 * Everything above, plus the block-level nodes only a long piece needs.
 *
 * This list does not actually spread PROSE (StarterKit and Link are
 * reconfigured here with their own options), so any node meant for both
 * profiles, including DynamicTagNode, has to be added to both arrays.
 */
const DOCUMENT = [
    StarterKit.configure({
        heading: { levels: [2, 3, 4, 5, 6] },
        link: false,
        // Replaced by the lowlight code block, which highlights as you type.
        codeBlock: false,
    }),
    // linkOnPaste: a URL pasted over selected words links them rather than
    // replacing them, which is what pasting a link onto text is meant to do.
    Link.configure({ openOnClick: false, linkOnPaste: true }),
    Image,
    Video,
    Callout,
    DynamicTagNode,
    CodeBlockMeta,
    ImageMeta,
];

/**
 * Build the extension list for a profile.
 *
 * @param {'prose'|'document'} profile
 * @param {{placeholder?: string|(() => string), mention?: object, slash?: object, callout?: object, image?: object, video?: object, dynamicTag?: object, dynamicTagSuggestion?: object, codeBlock?: object}} options
 */
export function extensionsFor(profile, { placeholder = '', mention = null, slash = null, callout = null, image = null, video = null, dynamicTag = null, dynamicTagSuggestion = null, codeBlock = null } = {}) {
    // The caller's version replaces the plain node, so the editor can draw it
    // with its node view while the renderer keeps the bare definition.
    const overrides = { callout, image, video, dynamicTag };

    const base = (profile === 'document' ? DOCUMENT : PROSE)
        .map((extension) => overrides[extension.name] ?? extension);

    return [
        ...base,
        PreserveKeys,
        Placeholder.configure({
            // Paragraphs only. An empty heading is a title waiting to be typed,
            // and telling it to "write something" describes the wrong thing.
            // Called rather than read, so a caller can vary the hint after the
            // editor is built. Trimming it on a narrow screen needs that.
            placeholder: ({ node }) => (node.type.name !== 'paragraph'
                ? ''
                : (typeof placeholder === 'function' ? placeholder() : placeholder)),
            showOnlyWhenEditable: true,
            // The line the caret is on, and only that one: several blank lines
            // would otherwise each repeat the same hint down the page.
            showOnlyCurrent: true,
        }),
        ...(mention ? [mention] : []),
        ...(slash ? [slash] : []),
        ...(dynamicTagSuggestion ? [dynamicTagSuggestion] : []),
        // Appended rather than swapped in: StarterKit's own code block is off,
        // so there is nothing in the base list to replace.
        ...(codeBlock && profile === 'document' ? [codeBlock] : []),
    ];
}
