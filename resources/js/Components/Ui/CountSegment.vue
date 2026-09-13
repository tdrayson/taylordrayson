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

const group = inject('countGroup', { variant: { value: 'plain' }, size: { value: 'md' } });

const PADDING = { sm: 'gap-1 px-2 py-0.5', md: 'gap-1.5 px-2.5 py-1' };

const boxed = computed(() => group.variant.value !== 'bare');

const classes = computed(() => cn(
    'count-segment inline-flex items-center font-medium text-neutral-500',
    boxed.value && 'is-boxed',
    props.padded && boxed.value && (PADDING[group.size.value] ?? PADDING.md),
));
</script>

<template>
    <component :is="as" :class="classes">
        <slot />
    </component>
</template>

<style scoped>
.count-segment {
    transition: background-color 150ms, color 150ms;
}

/* One rounded outline made of segments: each draws its own top and bottom edge,
   the ends add the sides, and a divider separates neighbours. */
.is-boxed {
    border-block: 1.5px solid var(--color-neutral-100);
}

.is-boxed:first-child {
    border-inline-start: 1.5px solid var(--color-neutral-100);
    border-start-start-radius: var(--radius-md);
    border-end-start-radius: var(--radius-md);
}

.is-boxed:last-child {
    border-inline-end: 1.5px solid var(--color-neutral-100);
    border-start-end-radius: var(--radius-md);
    border-end-end-radius: var(--radius-md);
}

.is-boxed + .is-boxed {
    border-inline-start: 1px solid var(--color-neutral-100);
}

a.is-boxed:hover,
.is-boxed:has(> * > button:hover) {
    background: var(--color-neutral-25);
}

/* The light row a quiet entry keeps: bare text that darkens on hover. */
a.count-segment:not(.is-boxed):hover,
.count-segment:not(.is-boxed):has(> * > button:hover) {
    color: var(--color-accent-700);
}
</style>
