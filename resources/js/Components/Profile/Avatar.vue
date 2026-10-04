<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: null },
    size: { type: String, default: 'size-13' },
    class: { type: [String, Array, Object], default: '' },
    imgClass: { type: [String, Array, Object], default: '' },
});

const page = usePage();

// Falls back to the site's own identity when a caller doesn't override
// src/alt, so every avatar without one stays in sync with the same config.
const src = computed(() => props.src ?? page.props.identity.avatar);
const alt = computed(() => props.alt ?? page.props.identity.name);

const classes = computed(() =>
    cn('inline-flex shrink-0 overflow-hidden rounded-full bg-accent-100', props.size, props.class),
);
</script>

<template>
    <span :class="classes">
        <img :src="src" :alt="alt" :class="cn('size-full bg-transparent object-cover object-top', imgClass)">
    </span>
</template>
