import StarterKit from '@tiptap/starter-kit';
import Link from '@tiptap/extension-link';
import Image from '@tiptap/extension-image';
import { Placeholder } from '@tiptap/extensions';
import { PreserveKeys, Video, Callout } from './nodes';

/**
 * What each kind of writing is allowed to contain.
 *
 * `document` is `prose` spread into a longer list rather than a second list of
 * its own. Two independent lists would agree on the day they were written and
 * drift the first time prose changed: a document is a note with more room, not
 * a different kind of text.
 */

/** Everything the shorter form allows. */
const PROSE = [
    StarterKit.configure({
        heading: false,
        blockquote: false,
        horizontalRule: false,
        codeBlock: false,
    }),
    Link.configure({ openOnClick: false }),
];

/** Everything above, plus the block-level nodes only a long piece needs. */
const DOCUMENT = [
    StarterKit.configure({
        heading: { levels: [2, 3, 4, 5, 6] },
    }),
    Link.configure({ openOnClick: false }),
    Image,
    Video,
    Callout,
];

/**
 * Build the extension list for a profile.
 *
 * @param {'prose'|'document'} profile
 * @param {{placeholder?: string, mention?: object}} options
 */
export function extensionsFor(profile, { placeholder = '', mention = null } = {}) {
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
    ];
}
