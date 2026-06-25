<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    label: { type: [String, Number], default: null },
    variant: { type: String, default: 'default' },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    default: 'bg-surface text-ink-2',
    accent: 'bg-accent-tint text-accent-active',
    outline: 'border border-line text-ink-2',
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
