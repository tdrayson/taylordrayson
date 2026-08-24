<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

/**
 * The menu behind both suggestion triggers, @-mentions and the "/" block list.
 * Purely presentational: the editor owns the lifecycle, because TipTap's
 * suggestion `render` factory runs once and its `onKeyDown` is only ever handed
 * the event, never the items or the insert command.
 */
const props = defineProps({
    items: { type: Array, default: () => [] },
    active: { type: Number, default: 0 },
    // Viewport rect of the caret, so the menu can sit under it.
    rect: { type: Object, default: null },
    // Re-reads that rect. The one above is a snapshot taken when the menu opens
    // and on every keystroke, which leaves it behind if the page scrolls in
    // between; this is how the menu catches up.
    getRect: { type: Function, default: null },
    emptyLabel: { type: String, default: 'Nothing to insert' },
});

defineEmits(['pick']);

const list = ref(null);

// Where the caret is now: the prop while typing, re-measured while scrolling.
const rect = ref(null);

watch(() => props.rect, (value) => (rect.value = value), { immediate: true });

function measure() {
    rect.value = props.getRect?.() ?? rect.value;
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

/**
 * Keep the armed row in view. The list scrolls, so arrowing past its edge would
 * otherwise move a selection the reader cannot see.
 */
watch(() => props.active, async () => {
    await nextTick();

    list.value
        ?.querySelector('[aria-selected="true"]')
        ?.scrollIntoView({ block: 'nearest' });
});

// Grouped for display while `items` stays flat, since the arrow keys move
// through one list regardless of where the group boundaries fall.
const groups = computed(() => {
    const out = [];

    props.items.forEach((item, index) => {
        const last = out[out.length - 1];

        if (last?.name === item.group) {
            last.rows.push({ ...item, index });

            return;
        }

        out.push({ name: item.group, rows: [{ ...item, index }] });
    });

    return out;
});

const MARGIN = 8;
const GAP = 6;
const WIDTH = 288;
// Enough for a group heading and two rows. Below this the menu is not worth
// showing in place, and the page scrolls to reach the rest.
const MIN_HEIGHT = 120;

/**
 * Always directly below the line being typed, and shortened to whatever room is
 * left rather than moved to where it fits. Flipping above the caret kept the
 * menu on screen but moved it out from under the words that filter it, so on a
 * short viewport it appeared to jump about at random.
 */
const style = computed(() => {
    if (! rect.value) {
        return { display: 'none' };
    }

    // The visual viewport, so a raised keyboard counts as the bottom edge.
    const viewport = window.visualViewport;
    const bottom = (viewport?.offsetTop ?? 0) + (viewport?.height ?? window.innerHeight);
    const width = Math.min(WIDTH, window.innerWidth - MARGIN * 2);
    const left = Math.max(MARGIN, Math.min(rect.value.left, window.innerWidth - width - MARGIN));

    // Lifted off the caret only as far as it takes to keep the whole menu on
    // screen. Anything hanging past the bottom edge cannot be scrolled to: the
    // menu scrolls its own overflow, and the page will not scroll a fixed
    // element into view.
    const top = Math.min(rect.value.bottom + GAP, bottom - MIN_HEIGHT - MARGIN);

    return {
        left: `${left}px`,
        width: `${width}px`,
        top: `${top}px`,
        maxHeight: `${bottom - top - MARGIN}px`,
    };
});
</script>

<template>
    <div
        ref="list"
        class="fixed z-50 overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
        :style="style"
        role="listbox"
    >
        <p v-if="! items.length" class="px-3 py-2 text-meta text-neutral-500">
            {{ emptyLabel }}
        </p>

        <div v-for="group in groups" :key="group.name">
            <p class="px-3 pb-1 pt-3 text-label uppercase text-neutral-500 first:pt-2">{{ group.name }}</p>

            <button
                v-for="row in group.rows"
                :key="row.id ?? row.label"
                type="button"
                role="option"
                :aria-selected="row.index === active"
                class="flex w-full items-baseline justify-between gap-3 px-3 py-1.5 text-left text-meta transition-colors"
                :class="row.index === active ? 'bg-accent-50 text-accent-700' : 'text-neutral-900 hover:bg-neutral-25'"
                @mousedown.prevent="$emit('pick', row)"
            >
                <span class="min-w-0 truncate">{{ row.label }}</span>
                <span v-if="row.detail" class="shrink-0 text-neutral-500">{{ row.detail }}</span>
            </button>
        </div>
    </div>
</template>
