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
    primary: 'bg-accent-500 text-white hover:bg-accent-700',
    secondary: 'border border-neutral-100 text-neutral-900 hover:bg-neutral-25',
    ghost: 'text-neutral-900 hover:text-accent-500',
    chip: 'bg-neutral-25 uppercase text-neutral-700 hover:bg-accent-50 hover:text-accent-700',
    destructive: 'bg-red-600 text-white hover:bg-red-700',
    link: 'text-accent-500 underline underline-offset-2 hover:text-accent-700',
};

const SIZES = {
    sm: 'gap-1.5 px-3 py-1.5 text-label',
    md: 'gap-1.5 px-4 py-2 text-meta',
    lg: 'gap-2 px-5 py-2.5 text-meta',
    icon: 'p-2',
};

const classes = computed(() =>
    cn(
        'inline-flex items-center justify-center font-semibold transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-40',
        props.pill ? 'rounded-full' : 'rounded-md',
        VARIANTS[props.variant] ?? VARIANTS.secondary,
        SIZES[props.size] ?? SIZES.md,
        // A link reads as text in the flow around it, so the size's box padding
        // would push it out of line with the paragraph beside it.
        props.variant === 'link' ? 'px-0' : '',
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
