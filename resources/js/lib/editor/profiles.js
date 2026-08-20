import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { PreserveKeys, CodeBlockMeta, ImageMeta, Video, Callout } from './nodes';

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
    }),
    Link.configure({ openOnClick: false, linkOnPaste: true }),
];

/** Everything above, plus the block-level nodes only a long piece needs. */
const DOCUMENT = [
    StarterKit.configure({
        heading: { levels: [2, 3, 4, 5, 6] },
    }),
    // linkOnPaste: a URL pasted over selected words links them rather than
    // replacing them, which is what pasting a link onto text is meant to do.
    Link.configure({ openOnClick: false, linkOnPaste: true }),
    Image,
    Video,
    Callout,
    CodeBlockMeta,
    ImageMeta,
];

/**
 * Build the extension list for a profile.
 *
 * @param {'prose'|'document'} profile
 * @param {{placeholder?: string, mention?: object, slash?: object, callout?: object, image?: object}} options
 */
export function extensionsFor(profile, { placeholder = '', mention = null, slash = null, callout = null, image = null } = {}) {
    // The caller's callout replaces the plain node, so the editor can draw it
    // with its picker while the renderer keeps the bare definition.
    const overrides = { callout, image };

    const base = (profile === 'document' ? DOCUMENT : PROSE)
        .map((extension) => overrides[extension.name] ?? extension);

    return [
        ...base,
        PreserveKeys,
        Placeholder.configure({
            // Paragraphs only. An empty heading is a title waiting to be typed,
            // and telling it to "write something" describes the wrong thing.
            placeholder: ({ node }) => (node.type.name === 'paragraph' ? placeholder : ''),
            showOnlyWhenEditable: true,
            // The line the caret is on, and only that one: several blank lines
            // would otherwise each repeat the same hint down the page.
            showOnlyCurrent: true,
        }),
        ...(mention ? [mention] : []),
        ...(slash ? [slash] : []),
    ];
}
