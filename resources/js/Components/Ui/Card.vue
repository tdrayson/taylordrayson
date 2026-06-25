<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    variant: { type: String, default: 'flat' },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    flat: 'bg-surface',
    elevated: 'bg-canvas shadow-card',
    outline: 'bg-canvas border border-line-2',
};

const classes = computed(() => cn('rounded-lg p-5', VARIANTS[props.variant] ?? VARIANTS.flat, props.class));
</script>

<template>
    <div :class="classes"><slot /></div>
</template>
