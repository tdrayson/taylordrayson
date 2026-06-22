<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    href: { type: String, default: null },
    variant: { type: String, default: 'secondary' },
    type: { type: String, default: 'button' },
});

const variants = {
    primary: 'bg-accent text-white hover:bg-accent-active',
    secondary: 'border border-line text-ink hover:bg-surface',
    plain: 'text-ink hover:text-accent',
};

const classes = computed(
    () =>
        `inline-flex items-center justify-center gap-1.5 rounded-md px-3 py-1.5 text-sm font-semibold transition-colors ${variants[props.variant] ?? variants.secondary}`
);

const tag = computed(() => (props.href ? Link : 'button'));
</script>

<template>
    <component :is="tag" :href="href || undefined" :type="href ? undefined : type" :class="classes">
        <slot />
    </component>
</template>
