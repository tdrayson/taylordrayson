<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { useSuggestionPosition } from '../../lib/editor/suggestionPosition';

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

// Enough for a group heading and two rows. Below this the menu is not worth
// showing in place, and the page scrolls to reach the rest.
const size = computed(() => ({ width: 288, minHeight: 120 }));

const { style } = useSuggestionPosition(computed(() => props.rect), props.getRect, size);
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
