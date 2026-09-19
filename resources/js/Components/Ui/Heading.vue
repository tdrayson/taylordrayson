<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    as: { type: [String, Object], default: 'h2' },
    size: { type: String, default: 'section' }, // 'section' | 'title' | 'display' | 'display-xl'
    class: { type: [String, Array, Object], default: '' },
});

// Tailwind's own leading is too airy for a heading, so every size names its own.
const SIZES = {
    section: 'text-lg font-bold leading-tight tracking-tight',
    title: 'text-2xl font-extrabold leading-tight tracking-tight',
    display: 'text-5xl font-extrabold tracking-tight',
    'display-xl': 'text-6xl font-extrabold tracking-tight sm:text-7xl',
};

const classes = computed(() => cn('font-display', SIZES[props.size] ?? SIZES.section, props.class));
</script>

<template>
    <component :is="as" :class="classes"><slot /></component>
</template>
