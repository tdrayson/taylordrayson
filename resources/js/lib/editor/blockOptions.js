/**
 * What each block type can be configured with, drawn by BlockOptions.vue.
 *
 * Keyed by node name, so the panel shows whatever the caret is inside without
 * knowing anything about the block itself.
 */

/** Languages offered for a code block, matching what the renderer highlights. */
const LANGUAGES = ['bash', 'css', 'html', 'javascript', 'json', 'php', 'python', 'sql', 'vue', 'yaml']
    .map((value) => ({ value, label: value }));

export const BLOCK_OPTIONS = {
    codeBlock: {
        type: 'codeBlock',
        label: 'Code',
        icon: 'SourceCodeIcon',
        fields: [
            { name: 'language', label: 'Language', type: 'select', options: LANGUAGES },
            { name: 'filename', label: 'Filename', type: 'text', wide: true },
            { name: 'lineNumbers', label: 'Line numbers', type: 'boolean' },
        ],
    },
    image: {
        type: 'image',
        label: 'Image',
        icon: 'Image01Icon',
        fields: [
            { name: 'alt', label: 'Alt text', type: 'text', wide: true },
            { name: 'caption', label: 'Caption', type: 'text', wide: true },
            {
                name: 'ratio',
                label: 'Aspect ratio',
                type: 'select',
                options: [
                    { value: 'original', label: 'Original' },
                    { value: '16/9', label: '16:9' },
                    { value: '4/3', label: '4:3' },
                    { value: '1/1', label: 'Square' },
                    { value: '3/4', label: 'Portrait' },
                ],
            },
        ],
    },
};

/** The definition for whichever block the caret is in, or null. */
export function blockOptionsFor(editor) {
    return Object.values(BLOCK_OPTIONS).find((definition) => editor.isActive(definition.type)) ?? null;
}
