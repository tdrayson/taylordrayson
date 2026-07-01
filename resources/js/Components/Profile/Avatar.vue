<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    src: { type: String, default: '/taylor-cutout.png' },
    alt: { type: String, default: 'Taylor Drayson' },
    size: { type: String, default: 'size-13' },
    class: { type: [String, Array, Object], default: '' },
    imgClass: { type: [String, Array, Object], default: '' },
});

const classes = computed(() =>
    cn('inline-flex shrink-0 overflow-hidden rounded-full bg-accent-100', props.size, props.class),
);
</script>

<template>
    <span :class="classes">
        <img :src="src" :alt="alt" :class="cn('size-full object-cover object-top', imgClass)">
    </span>
</template>
