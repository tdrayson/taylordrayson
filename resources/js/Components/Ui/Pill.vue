<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    label: { type: [String, Number], default: null },
    variant: { type: String, default: 'default' },
    href: { type: String, default: null },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    default: 'bg-neutral-25 text-neutral-700',
    accent: 'bg-accent-50 text-accent-700',
    outline: 'border border-neutral-100 text-neutral-700',
};

// Linkable chips (e.g. tags) mirror their hover state in focus-visible, per
// the site's a11y convention, and get a visible focus ring since they carry
// no underline of their own.
const classes = computed(() =>
    cn(
        'inline-flex items-center rounded-full px-2.5 py-1 text-label uppercase',
        VARIANTS[props.variant] ?? VARIANTS.default,
        props.href
            ? 'transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:bg-accent-50 focus-visible:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-1'
            : '',
        props.class,
    ),
);
</script>

<template>
    <component :is="href ? Link : 'span'" :href="href ?? undefined" :class="classes"><slot>{{ label }}</slot></component>
</template>
