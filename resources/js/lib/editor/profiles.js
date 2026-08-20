import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { PreserveKeys, Video, Callout } from './nodes';

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
];

/**
 * Build the extension list for a profile.
 *
 * @param {'prose'|'document'} profile
 * @param {{placeholder?: string, mention?: object, slash?: object}} options
 */
export function extensionsFor(profile, { placeholder = '', mention = null, slash = null } = {}) {
    const base = profile === 'document' ? DOCUMENT : PROSE;

    return [
        ...base,
        PreserveKeys,
        Placeholder.configure({
            placeholder,
            // A document is a page you live in, so every empty line offers the
            // hint. A note is not: a blank third line mid-note is a pause in
            // writing, and drawing the hint there interrupts it.
            showOnlyWhenEditable: true,
            showOnlyCurrent: profile !== 'document',
        }),
        ...(mention ? [mention] : []),
        ...(slash ? [slash] : []),
    ];
}
