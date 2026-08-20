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
                'mention',
            ],
            attributes: {
                _key: { default: null, rendered: false },
            },
        }];
    },
});

/**
 * `language` comes from StarterKit's code block, but `filename` and
 * `lineNumbers` do not exist there. Undeclared attributes are dropped on load,
 * so without this, opening an article silently strips both from every code
 * block it contains.
 */
export const CodeBlockMeta = Extension.create({
    name: 'codeBlockMeta',

    addGlobalAttributes() {
        return [{
            types: ['codeBlock'],
            attributes: {
                filename: { default: null, rendered: false },
                lineNumbers: { default: null, rendered: false },
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
