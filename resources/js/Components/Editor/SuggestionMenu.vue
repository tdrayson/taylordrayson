<script setup>
import { computed } from 'vue';

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
    emptyLabel: { type: String, default: 'Nothing to insert' },
});

defineEmits(['pick']);

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
const WIDTH = 288;
const MIN_SPACE_BELOW = 220;

/**
 * Below the caret when there is room, above it when there is not. With a
 * keyboard up the space below is a sliver, and a menu pinned under the caret
 * covers the line being typed. Flipping anchors the bottom edge instead, so the
 * menu never has to know its own height.
 */
const style = computed(() => {
    if (! props.rect) {
        return { display: 'none' };
    }

    const viewport = window.visualViewport;
    const height = viewport?.height ?? window.innerHeight;
    const width = Math.min(WIDTH, window.innerWidth - MARGIN * 2);
    const left = Math.max(MARGIN, Math.min(props.rect.left, window.innerWidth - width - MARGIN));
    const below = height - props.rect.bottom;

    if (below < MIN_SPACE_BELOW && props.rect.top > below) {
        return { left: `${left}px`, width: `${width}px`, bottom: `${window.innerHeight - props.rect.top + 6}px` };
    }

    return { left: `${left}px`, width: `${width}px`, top: `${props.rect.bottom + 6}px` };
});
</script>

<template>
    <div
        class="fixed z-50 max-h-64 overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
        :style="style"
        role="listbox"
    >
        <p v-if="! items.length" class="px-3 py-2 text-meta text-neutral-500">
            {{ emptyLabel }}
        </p>

        <div v-for="group in groups" :key="group.name">
            <p class="px-3 pb-1 pt-2 text-label uppercase text-neutral-500">{{ group.name }}</p>

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
