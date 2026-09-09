<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    // Corner radius, matching the element being stood in for.
    variant: { type: String, default: 'default' },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    default: 'rounded-md',
    card: 'rounded-lg',
};

const classes = computed(() =>
    cn(
        'animate-pulse bg-neutral-25 motion-reduce:animate-none',
        VARIANTS[props.variant] ?? VARIANTS.default,
        props.class,
    ),
);
</script>

<template>
    <!-- aria-hidden: the wait belongs to the region this fills, and the
         placeholder shape says nothing to a screen reader. -->
    <div :class="classes" aria-hidden="true" />
</template>
