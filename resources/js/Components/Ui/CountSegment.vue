<script setup>
import { computed, inject } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    // What to render as: a span for a static count, a button or Link when the
    // segment does something.
    as: { type: [String, Object], default: 'span' },
    // A CSS colour, normally one of the --color-count-* tokens.
    colour: { type: String, default: null },
    // Drawn neutral whatever the group's variant, so a zero never shows colour:
    // on a timeline of mostly unanswered entries, colour has to mean something happened.
    muted: { type: Boolean, default: false },
    // Off when the segment wraps its own interactive element, which then takes
    // the padding so its hit area is the whole segment.
    padded: { type: Boolean, default: true },
});

const group = inject('countGroup', { variant: { value: 'tinted' }, size: { value: 'md' } });

const PADDING = { sm: 'gap-1 px-2 py-0.5', md: 'gap-1.5 px-2.5 py-1' };

/** The variant a segment is drawn in: its group's, or plain for a zero inside a box. */
const variant = computed(() => (props.muted && group.variant.value !== 'bare' ? 'plain' : group.variant.value));

const classes = computed(() => cn(
    'count-segment inline-flex items-center font-medium',
    `is-${variant.value}`,
    props.padded && variant.value !== 'bare' && (PADDING[group.size.value] ?? PADDING.md),
));

/** Hands the colour to the styles as one custom property to derive wash, edge and ink from. */
const style = computed(() => ({ '--segment': props.colour ?? 'var(--color-neutral-500)' }));
</script>

<template>
    <component :is="as" :class="classes" :style="style">
        <slot />
    </component>
</template>

<style scoped>
/* One rounded outline made of segments: each draws its own top and bottom edge
   and the two ends add the sides, so a segment's colour runs into its corners. */
.count-segment {
    transition: background-color 150ms, color 150ms;
}

.count-segment:not(.is-bare) {
    border-block: 1.5px solid var(--edge);
}

.count-segment:not(.is-bare):first-child {
    border-inline-start: 1.5px solid var(--edge);
    border-start-start-radius: var(--radius-md);
    border-end-start-radius: var(--radius-md);
}

.count-segment:not(.is-bare):last-child {
    border-inline-end: 1.5px solid var(--edge);
    border-start-end-radius: var(--radius-md);
    border-end-end-radius: var(--radius-md);
}

/* Mixed toward the page's own surface and ink rather than fixed lightness, so
   one token reads in both themes: the neutrals flip in dark mode. */
.is-tinted {
    --edge: color-mix(in oklch, var(--segment) 75%, var(--color-neutral-900));
    --wash: color-mix(in oklch, var(--segment) 12%, var(--color-neutral-0));
    --wash-hover: color-mix(in oklch, var(--segment) 22%, var(--color-neutral-0));
    background: var(--wash);
    color: color-mix(in oklch, var(--segment) 70%, var(--color-neutral-900));
}

.is-icons,
.is-plain {
    --edge: var(--color-neutral-100);
    --wash-hover: var(--color-neutral-25);
}

.is-icons {
    color: var(--color-neutral-700);
}

.is-icons :deep(svg) {
    color: var(--segment);
}

.is-plain {
    color: var(--color-neutral-500);
}

.count-segment + .is-icons,
.count-segment + .is-plain {
    border-inline-start: 1px solid var(--color-neutral-100);
}

a.count-segment:not(.is-bare):hover,
.count-segment:not(.is-bare):has(> * > button:hover) {
    background: var(--wash-hover);
}

/* The light row a quiet entry keeps: bare text that darkens on hover. */
.is-bare {
    color: var(--color-neutral-500);
}

a.is-bare:hover,
.is-bare:has(> * > button:hover) {
    color: var(--color-accent-700);
}
</style>
