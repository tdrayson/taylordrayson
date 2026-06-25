<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'secondary' },
    size: { type: String, default: 'md' },
    pill: { type: Boolean, default: false },
    type: { type: String, default: 'button' },
    disabled: { type: Boolean, default: false },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    primary: 'bg-accent text-white hover:bg-accent-active',
    secondary: 'border border-line text-ink hover:bg-surface',
    ghost: 'text-ink hover:text-accent',
    chip: 'bg-surface uppercase text-ink-2 hover:bg-accent-tint hover:text-accent-active',
    destructive: 'bg-red-600 text-white hover:bg-red-700',
    link: 'text-accent underline underline-offset-2 hover:text-accent-active',
};

const SIZES = {
    sm: 'gap-1.5 px-3 py-1.5 text-label',
    md: 'gap-1.5 px-4 py-2 text-meta',
    lg: 'gap-2 px-5 py-2.5 text-meta',
    icon: 'p-2',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center justify-center font-semibold transition-colors focus-visible:outline-none disabled:pointer-events-none disabled:opacity-40',
        props.pill ? 'rounded-full' : 'rounded-md',
        VARIANTS[props.variant] ?? VARIANTS.secondary,
        SIZES[props.size] ?? SIZES.md,
        props.class,
    ),
);

const tag = computed(() => (props.href ? Link : 'button'));
</script>

<template>
    <component
        :is="tag"
        :href="href || undefined"
        :type="href ? undefined : type"
        :disabled="href ? undefined : disabled || undefined"
        :class="classes"
    >
        <slot />
    </component>
</template>
