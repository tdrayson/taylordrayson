import { ref, watch, nextTick } from 'vue';

/**
 * Shared keyboard navigation for a search-and-select listbox (the command
 * palette and the archive taxonomy filter both use this): a wrapping
 * active-index driven by the arrow keys, Enter to select the highlight, and the
 * highlighted option kept scrolled into view.
 *
 * The option element for the highlighted index must carry `data-active="true"`
 * so it can be scrolled into view.
 *
 * @param {import('vue').Ref<Array>} items  Reactive list of navigable options.
 * @param {object} opts
 * @param {import('vue').Ref<HTMLElement|null>} opts.listEl  Scroll container whose [data-active] child is kept visible.
 * @param {(item: any) => void} opts.onSelect  Called with the highlighted item on Enter.
 * @returns {{ activeIndex: import('vue').Ref<number>, move: (delta: number) => void, onKeydown: (event: KeyboardEvent) => void }}
 */
export function useListboxNavigation(items, { listEl, onSelect }) {
    const activeIndex = ref(0);

    // Wrap around the ends so holding an arrow key cycles rather than dead-ends.
    function move(delta) {
        const count = items.value.length;

        if (count === 0) {
            return;
        }

        activeIndex.value = (activeIndex.value + delta + count) % count;

        nextTick(() => {
            listEl.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
        });
    }

    // Arrow keys move the highlight; Enter selects the highlighted item.
    function onKeydown(event) {
        if (event.key === 'ArrowDown') {
            event.preventDefault();
            move(1);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            move(-1);
        } else if (event.key === 'Enter') {
            event.preventDefault();
            onSelect(items.value[activeIndex.value]);
        }
    }

    // Reset the highlight to the top whenever the option set changes.
    watch(items, () => {
        activeIndex.value = 0;
    });

    return { activeIndex, move, onKeydown };
}
