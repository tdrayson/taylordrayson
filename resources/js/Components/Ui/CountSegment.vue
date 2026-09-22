<script setup>
import { computed, inject } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    // What to render as: a span for a static count, a button or Link when the
    // segment does something.
    as: { type: [String, Object], default: 'span' },
    // Off when the segment wraps its own interactive element, which then takes
    // the padding so its hit area is the whole segment.
    padded: { type: Boolean, default: true },
});

const group = inject('countGroup', { size: { value: 'md' } });

const PADDING = { sm: 'gap-1 px-2 py-0.5', md: 'gap-1.5 px-2.5 py-1' };

const classes = computed(() => cn(
    'count-segment inline-flex items-center font-medium text-neutral-500',
    props.padded && (PADDING[group.size.value] ?? PADDING.md),
));
</script>

<template>
    <component :is="as" :class="classes">
        <slot />
    </component>
</template>

<style scoped>
/* One rounded outline made of segments: each draws its own top and bottom edge,
   the ends add the sides, and a divider separates neighbours. */
.count-segment {
    border-block: 1.5px solid var(--color-neutral-100);
    transition: background-color 150ms;
}

.count-segment:first-child {
    border-inline-start: 1.5px solid var(--color-neutral-100);
    border-start-start-radius: var(--radius-md);
    border-end-start-radius: var(--radius-md);
}

.count-segment:last-child {
    border-inline-end: 1.5px solid var(--color-neutral-100);
    border-start-end-radius: var(--radius-md);
    border-end-end-radius: var(--radius-md);
}

.count-segment + .count-segment {
    border-inline-start: 1px solid var(--color-neutral-100);
}

a.count-segment:hover,
.count-segment:has(> * > button:hover) {
    background: var(--color-neutral-25);
}

/* The segment is what gets the ring, drawn over its own edge: rounded where the
   group ends, square where it joins a neighbour. */
a.count-segment:focus-visible,
.count-segment:has(> * > button:focus-visible) {
    outline: 2px solid var(--color-accent-500);
    outline-offset: -1.5px;
}

.count-segment > :deep(* > button:focus-visible) {
    outline: none;
}
</style>
