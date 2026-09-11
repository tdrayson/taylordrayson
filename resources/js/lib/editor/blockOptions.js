/**
 * What each block type can be configured with, drawn by BlockSettings.vue.
 *
 * Keyed by node name, so a block reaches its own settings by name and the form
 * knows nothing about the block itself. A new configurable block needs a
 * definition here and a settings button in its node view.
 */

/**
 * Only the grammars registered in lowlight.js and CodeBlock.vue. Offering one
 * that is not registered looks like highlighting is broken, when in fact the
 * language was never loaded.
 */
const LANGUAGES = ['bash', 'css', 'javascript', 'json', 'php', 'sql', 'typescript', 'xml', 'yaml']
    .map((value) => ({ value, label: value }));

export const BLOCK_OPTIONS = {
    codeBlock: {
        type: 'codeBlock',
        label: 'Code',
        fields: [
            { name: 'language', label: 'Language', type: 'select', options: LANGUAGES, empty: 'Auto' },
            { name: 'filename', label: 'Filename', type: 'text' },
            { name: 'lineNumbers', label: 'Line numbers', type: 'boolean' },
        ],
    },
    image: {
        type: 'image',
        label: 'Image',
        fields: [
            { name: 'alt', label: 'Alt text', type: 'text' },
            { name: 'caption', label: 'Caption', type: 'text' },
            {
                name: 'ratio',
                label: 'Aspect ratio',
                type: 'select',
                // Unset is the original ratio, which is also what the legacy
                // "original" value means, so it needs no choice of its own.
                empty: 'Original',
                options: [
                    { value: '16/9', label: '16:9' },
                    { value: '4/3', label: '4:3' },
                    { value: '1/1', label: 'Square' },
                    { value: '3/4', label: 'Portrait' },
                ],
            },
        ],
    },
    video: {
        type: 'video',
        label: 'Video',
        fields: [
            { name: 'url', label: 'Video URL', type: 'text' },
            { name: 'caption', label: 'Caption', type: 'text' },
            { name: 'poster', label: 'Thumbnail URL', type: 'text' },
        ],
    },
};
