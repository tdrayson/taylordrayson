import { Extension } from '@tiptap/core';
import { PluginKey } from '@tiptap/pm/state';
import Suggestion from '@tiptap/suggestion';

/**
 * A suggestion trigger, built on the same plugin @-mentions and "/" both use so
 * the two menus behave identically: same keys, same dismissal, same placement.
 *
 * Named per trigger, and given its own plugin key: the suggestion plugin
 * defaults to a shared one, and ProseMirror refuses two keyed plugins with the
 * same key, which takes the whole editor down rather than just the second menu.
 *
 * @param {string} name
 */
export function suggestionExtension(name) {
    return Extension.create({
        name,

        addOptions() {
            return {
                suggestion: {
                    // Mid-word triggers are paths and email addresses, not menus.
                    allowSpaces: false,
                    startOfLine: false,
                },
            };
        },

        addProseMirrorPlugins() {
            return [Suggestion({
                editor: this.editor,
                pluginKey: new PluginKey(name),
                ...this.options.suggestion,
            })];
        },
    });
}
