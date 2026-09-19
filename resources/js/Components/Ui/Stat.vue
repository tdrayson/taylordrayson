<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    as: { type: [String, Object], default: 'span' },
    size: { type: String, default: 'default' }, // 'default' | 'lg'
    class: { type: [String, Array, Object], default: '' },
});

const SIZES = {
    default: 'text-3xl',
    lg: 'text-5xl',
};

const classes = computed(() =>
    cn(
        'font-display font-extrabold leading-none tracking-tight tabular-nums',
        SIZES[props.size] ?? SIZES.default,
        props.class,
    ),
);
</script>

<template>
    <component :is="as" :class="classes"><slot /></component>
</template>
