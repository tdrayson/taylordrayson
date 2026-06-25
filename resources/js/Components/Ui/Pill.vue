<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    label: { type: [String, Number], default: null },
    variant: { type: String, default: 'default' },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    default: 'bg-neutral-25 text-neutral-700',
    accent: 'bg-accent-50 text-accent-700',
    outline: 'border border-neutral-100 text-neutral-700',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center rounded-full px-2.5 py-1 text-label uppercase',
        VARIANTS[props.variant] ?? VARIANTS.default,
        props.class,
    ),
);
</script>

<template>
    <span :class="classes"><slot>{{ label }}</slot></span>
</template>
