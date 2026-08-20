/**
 * The blocks the slash menu can insert.
 *
 * `requires` names the schema node each one needs, so a profile that does not
 * load a node simply never offers it: the prose profile drops headings and code
 * without this file knowing which profiles exist.
 */

/** Replace the typed "/query" with the block, so the trigger never survives. */
const at = (editor, range) => editor.chain().focus().deleteRange(range);

export const BLOCKS = [
    ...[2, 3, 4].map((level) => ({
        id: `h${level}`,
        group: 'Headings',
        label: `Heading ${level}`,
        detail: '#'.repeat(level),
        requires: 'heading',
        run: (editor, range) => at(editor, range).setNode('heading', { level }).run(),
    })),
    {
        id: 'paragraph',
        group: 'Text',
        label: 'Text',
        requires: 'paragraph',
        run: (editor, range) => at(editor, range).setNode('paragraph').run(),
    },
    {
        id: 'bulletList',
        group: 'Text',
        label: 'Bulleted list',
        requires: 'bulletList',
        run: (editor, range) => at(editor, range).toggleBulletList().run(),
    },
    {
        id: 'orderedList',
        group: 'Text',
        label: 'Numbered list',
        requires: 'orderedList',
        run: (editor, range) => at(editor, range).toggleOrderedList().run(),
    },
    {
        id: 'blockquote',
        group: 'Text',
        label: 'Quote',
        requires: 'blockquote',
        run: (editor, range) => at(editor, range).toggleBlockquote().run(),
    },
    {
        id: 'codeBlock',
        group: 'Blocks',
        label: 'Code block',
        requires: 'codeBlock',
        run: (editor, range) => at(editor, range).toggleCodeBlock().run(),
    },
    {
        id: 'callout',
        group: 'Blocks',
        label: 'Callout',
        requires: 'callout',
        run: (editor, range) => at(editor, range)
            .insertContent({ type: 'callout', attrs: { variant: 'note' }, content: [{ type: 'paragraph' }] })
            .run(),
    },
    {
        id: 'divider',
        group: 'Blocks',
        label: 'Divider',
        requires: 'horizontalRule',
        run: (editor, range) => at(editor, range).setHorizontalRule().run(),
    },
    {
        id: 'image',
        group: 'Blocks',
        label: 'Image',
        detail: 'by URL',
        requires: 'image',
        // Prompted rather than uploaded: an image in the body stores a plain
        // URL, and nothing yet gives an upload a durable one.
        run: (editor, range) => {
            const url = window.prompt('Image URL');

            if (! url) {
                at(editor, range).run();

                return;
            }

            at(editor, range).insertContent({ type: 'image', attrs: { url } }).run();
        },
    },
    {
        id: 'video',
        group: 'Blocks',
        label: 'Video',
        detail: 'by URL',
        requires: 'video',
        run: (editor, range) => {
            const url = window.prompt('Video URL');

            if (! url) {
                at(editor, range).run();

                return;
            }

            at(editor, range).insertContent({ type: 'video', attrs: { url } }).run();
        },
    },
];

/** The blocks this editor's schema can actually hold. */
export function blocksFor(editor, query = '') {
    const needle = query.trim().toLowerCase();

    return BLOCKS
        .filter((block) => editor.schema.nodes[block.requires])
        .filter((block) => needle === '' || block.label.toLowerCase().includes(needle));
}
