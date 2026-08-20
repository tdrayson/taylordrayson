import { Node, Extension } from '@tiptap/core';

/**
 * The node types the Portable Text converters produce which StarterKit has no
 * equivalent for, plus the extension that keeps `_key` alive across an edit.
 *
 * Read alongside lib/portable-text/: every node name here has to match what
 * toProseMirror.js emits, or ProseMirror rejects the document on load.
 */

/**
 * Keys are how a save stays a diff rather than a rewrite. ProseMirror drops any
 * attribute a node does not declare, so every type that carries one has to say
 * so, and a global attribute is the only way to do that without redefining
 * StarterKit's nodes one by one.
 */
export const PreserveKeys = Extension.create({
    name: 'preserveKeys',

    addGlobalAttributes() {
        return [{
            types: [
                'paragraph',
                'heading',
                'blockquote',
                'listItem',
                'codeBlock',
                'horizontalRule',
                'image',
                'video',
                'callout',
            ],
            attributes: {
                _key: { default: null, rendered: false },
            },
        }];
    },
});

/** Tab handling for code blocks. Their attributes live on the node itself. */
export const CodeBlockMeta = Extension.create({
    name: 'codeBlockMeta',

    /**
     * Tab indents inside a code block rather than leaving it. Everywhere else
     * Tab belongs to the browser, moving focus to the next control, so this is
     * deliberately scoped to the one place indentation is the point.
     */
    addKeyboardShortcuts() {
        return {
            Tab: () => {
                if (! this.editor.isActive('codeBlock')) {
                    return false;
                }

                return this.editor.commands.insertContent('    ');
            },
            'Shift-Tab': () => {
                if (! this.editor.isActive('codeBlock')) {
                    return false;
                }

                const { state } = this.editor;
                const { from } = state.selection;
                const line = state.doc.textBetween(Math.max(0, from - 4), from);

                // Only unindent a full stop's worth of spaces, so Shift-Tab
                // never eats code.
                return line === '    '
                    ? this.editor.commands.deleteRange({ from: from - 4, to: from })
                    : true;
            },
        };
    },

});

/**
 * TipTap's image node speaks `src`, while the stored document speaks `url` and
 * carries a caption and alt text alongside it. Declared here so none of them
 * are dropped on load.
 */
export const ImageMeta = Extension.create({
    name: 'imageMeta',

    addGlobalAttributes() {
        return [{
            types: ['image'],
            attributes: {
                url: { default: null, rendered: false },
                alt: { default: null, rendered: false },
                ratio: { default: null, rendered: false },
                caption: { default: null, rendered: false },
                width: { default: null, rendered: false },
                height: { default: null, rendered: false },
            },
        }];
    },
});

/**
 * A video embed. Streams from an external host rather than being uploaded, so
 * the node holds a URL and never a file.
 */
export const Video = Node.create({
    name: 'video',
    group: 'block',
    atom: true,
    draggable: true,

    addAttributes() {
        return {
            url: { default: null },
            caption: { default: null },
            width: { default: null },
            height: { default: null },
        };
    },

    parseHTML() {
        return [{ tag: 'div[data-video]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['div', { 'data-video': '', ...HTMLAttributes }];
    },
});

/**
 * A callout panel. Its body is real blocks rather than an attribute, so the
 * text inside stays editable in place.
 */
export const Callout = Node.create({
    name: 'callout',
    group: 'block',
    content: 'block+',
    defining: true,

    addAttributes() {
        return {
            variant: { default: 'note' },
        };
    },

    parseHTML() {
        return [{ tag: 'aside[data-callout]' }];
    },

    renderHTML({ HTMLAttributes }) {
        return ['aside', { 'data-callout': '', ...HTMLAttributes }, 0];
    },
});

