import { Extension } from '@tiptap/core';
import Suggestion from '@tiptap/suggestion';

/**
 * The "/" block menu, built on the same suggestion plugin as @-mentions so both
 * menus behave identically: same keys, same dismissal, same positioning.
 *
 * The extension only wires the trigger. Which blocks appear, and what each one
 * inserts, lives in blocks.js.
 */
export const SlashCommands = Extension.create({
    name: 'slashCommands',

    addOptions() {
        return {
            suggestion: {
                char: '/',
                // Mid-word slashes are paths and dates, not a block menu.
                allowSpaces: false,
                startOfLine: false,
                command: ({ editor, range, props }) => props.run(editor, range),
            },
        };
    },

    addProseMirrorPlugins() {
        return [Suggestion({ editor: this.editor, ...this.options.suggestion })];
    },
});
