import { ref, watch } from 'vue';

/**
 * Arrow-key navigation for a dropdown list, shared by every combobox here: the
 * suggestions are the only way to pick an existing tag, airport or timezone.
 *
 * @param {import('vue').Ref<Array>} items
 */
export function useListNavigation(items, { onSelect, onDismiss } = {}) {
    const active = ref(0);

    // A changed list means the old index points at something else, or at
    // nothing; arming the first row is what Enter should act on.
    watch(items, () => {
        active.value = 0;
    });

    function move(delta) {
        const count = items.value.length;

        if (! count) {
            return;
        }

        active.value = (active.value + delta + count) % count;
    }

    /** @returns {boolean} whether the key was handled and should not bubble. */
    function onKeydown(event) {
        if (! items.value.length) {
            return false;
        }

        switch (event.key) {
            case 'ArrowDown':
                event.preventDefault();
                move(1);

                return true;
            case 'ArrowUp':
                event.preventDefault();
                move(-1);

                return true;
            case 'Enter':
                event.preventDefault();
                onSelect?.(items.value[active.value]);

                return true;
            case 'Escape':
                // Claimed so one Escape does not also cancel the form behind it.
                event.stopPropagation();
                onDismiss?.();

                return true;
            default:
                return false;
        }
    }

    return { active, onKeydown };
}
