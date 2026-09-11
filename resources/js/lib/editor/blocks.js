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
        label: 'Paragraph',
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
        // Always a note. The kind is changed on the block itself, where you can
        // see the panel you are changing.
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
        requires: 'image',
        // Inserted empty: the block itself offers the dropzone and the URL
        // field, which beats answering a prompt before you can see anything.
        run: (editor, range) => at(editor, range).insertContent({ type: 'image' }).run(),
    },
    {
        id: 'video',
        group: 'Blocks',
        label: 'Video',
        detail: 'YouTube, Vimeo or a file',
        requires: 'video',
        // Inserted empty for the same reason as the image: the block carries its
        // own URL field, and it can show you what you pasted.
        run: (editor, range) => at(editor, range).insertContent({ type: 'video' }).run(),
    },
    {
        id: 'file',
        group: 'Blocks',
        label: 'File',
        detail: 'A download, uploaded or from a release',
        requires: 'file',
        // Empty like the image and video: the block itself offers the dropzone
        // and the release fields.
        run: (editor, range) => at(editor, range).insertContent({ type: 'file' }).run(),
    },
];

/** The blocks this editor's schema can actually hold. */
export function blocksFor(editor, query = '') {
    const needle = query.trim().toLowerCase();

    return BLOCKS
        .filter((block) => editor.schema.nodes[block.requires])
        .filter((block) => needle === '' || block.label.toLowerCase().includes(needle));
}
