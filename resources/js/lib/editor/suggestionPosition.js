import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';

const MARGIN = 8;
const GAP = 6;

/**
 * Fixed-position placement for a menu anchored under the editor caret, shared
 * by every suggestion trigger (@, /, {). Always directly below the caret and
 * shortened to whatever room is left rather than moved to where it fits:
 * flipping above the caret keeps the menu on screen but moves it out from
 * under the words that filter it.
 *
 * @param {import('vue').Ref<object|null>} rectRef snapshot of the caret rect
 * @param {Function|null} getRect re-measures the caret rect, for scroll/resize
 * @param {import('vue').Ref<{width: number, minHeight: number}>} sizeRef
 */
export function useSuggestionPosition(rectRef, getRect, sizeRef) {
    const rect = ref(null);

    watch(rectRef, (value) => (rect.value = value), { immediate: true });

    function measure() {
        rect.value = getRect?.() ?? rect.value;
    }

    onMounted(() => {
        // Capturing, since the editor may sit in its own scrolling container.
        window.addEventListener('scroll', measure, true);
        window.addEventListener('resize', measure);
        window.visualViewport?.addEventListener('resize', measure);
        window.visualViewport?.addEventListener('scroll', measure);
    });

    onBeforeUnmount(() => {
        window.removeEventListener('scroll', measure, true);
        window.removeEventListener('resize', measure);
        window.visualViewport?.removeEventListener('resize', measure);
        window.visualViewport?.removeEventListener('scroll', measure);
    });

    const style = computed(() => {
        if (! rect.value) {
            return { display: 'none' };
        }

        const { width, minHeight } = sizeRef.value;

        // The visual viewport, so a raised keyboard counts as the bottom edge.
        const viewport = window.visualViewport;
        const bottom = (viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight);
        const resolvedWidth = Math.min(width, window.innerWidth - MARGIN * 2);
        const left = Math.max(MARGIN, Math.min(rect.value.left, window.innerWidth - resolvedWidth - MARGIN));

        // Lifted off the caret only as far as it takes to keep the whole menu
        // on screen. Anything hanging past the bottom edge cannot be scrolled
        // to: the menu scrolls its own overflow, and the page will not scroll
        // a fixed element into view.
        const top = Math.min(rect.value.bottom + GAP, bottom - minHeight - MARGIN);

        return {
            left: `${left}px`,
            width: `${resolvedWidth}px`,
            top: `${top}px`,
            maxHeight: `${bottom - top - MARGIN}px`,
        };
    });

    return { style };
}
