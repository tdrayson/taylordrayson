/**
 * Key handling shared by the editor's two suggestion menus, @-mentions and "/".
 *
 * TipTap's suggestion plugin hands `onKeyDown` only the event, so the menu
 * state and the pick callback have to be closed over here rather than passed.
 *
 * @param {{open: boolean, items: Array, active: number}} state
 * @param {(item: any) => void} pick
 * @returns {(event: KeyboardEvent) => boolean} whether the key was handled.
 */
export function suggestionKeys(state, pick) {
    return (event) => {
        if (! state.open) {
            return false;
        }

        const count = Math.max(state.items.length, 1);

        if (event.key === 'ArrowDown') {
            state.active = (state.active + 1) % count;

            return true;
        }

        if (event.key === 'ArrowUp') {
            state.active = (state.active - 1 + state.items.length) % count;

            return true;
        }

        if (event.key === 'Enter' || event.key === 'Tab') {
            pick(state.items[state.active]);

            return true;
        }

        // The plugin exits on Escape whatever this returns. Claiming the key is
        // what stops it bubbling to the surrounding form, where one Escape
        // would both close the menu and cancel the edit behind it.
        if (event.key === 'Escape') {
            state.open = false;

            return true;
        }

        return false;
    };
}
