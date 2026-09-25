<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    // A plain anchor when set: the vCard is a file download, never an Inertia visit.
    href: { type: String, default: null },
    type: { type: String, default: 'button' },
    outline: { type: Boolean, default: false },
    variant: { type: String, default: 'personal' },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    personal: {
        solid: 'bg-accent-500 font-display font-semibold text-white hover:brightness-110',
        outline: 'border border-accent-500 bg-neutral-0 font-display font-semibold text-neutral-900 hover:bg-accent-50 dark:bg-neutral-25',
    },
    business: {
        solid: 'bg-tinker-500 font-montserrat font-bold text-white hover:brightness-110',
        outline: 'border border-tinker-500 bg-neutral-0 font-montserrat font-bold text-neutral-900 hover:bg-tinker-50 dark:bg-neutral-25',
    },
};

const classes = computed(() => cn(
    'flex h-14 w-full items-center justify-center gap-2.5 rounded-lg text-lg transition',
    (VARIANTS[props.variant] ?? VARIANTS.personal)[props.outline ? 'outline' : 'solid'],
    props.class,
));
</script>

<template>
    <a v-if="href" :href="href" :class="classes"><slot /></a>
    <button v-else :type="type" :class="classes"><slot /></button>
</template>
