<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    as: { type: [String, Object], default: 'div' },
    class: { type: [String, Array, Object], default: '' },
});

const classes = computed(() => cn('text-2xs font-semibold uppercase tracking-wider', props.class));
</script>

<template>
    <component :is="as" :class="classes"><slot /></component>
</template>
