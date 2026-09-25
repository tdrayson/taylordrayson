<script setup>
import { cn } from '../../lib/cn.js';
import Icon from '../Ui/Icon.vue';
import { SURFACE } from './shared.js';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: String, required: true },
    iconClass: { type: String, default: 'text-accent-500' },
    // Spoken instead of the short visible label, e.g. "Call me" for "Call".
    ariaLabel: { type: String, default: null },
    external: { type: Boolean, default: false },
    variant: { type: String, default: 'personal' },
    // 'square' fits four across; 'compact' is a short tile for three across.
    shape: { type: String, default: 'square' },
});

const SHAPES = {
    square: 'aspect-square gap-1.5 p-2 text-xs',
    compact: 'gap-1 px-2 py-3 text-sm',
};

const LABELS = {
    personal: 'font-display font-semibold',
    business: 'font-open-sans font-semibold',
};
</script>

<template>
    <a
        :href="href"
        :target="external ? '_blank' : undefined"
        :rel="external ? 'noopener noreferrer' : undefined"
        :aria-label="ariaLabel ? `${ariaLabel}${external ? ', opens in a new tab' : ''}` : undefined"
        :class="cn('flex flex-col items-center justify-center rounded-lg transition-colors hover:border-neutral-200', SHAPES[shape], SURFACE)"
    >
        <Icon :name="icon" :class="[iconClass, shape === 'square' ? 'size-6' : 'size-5']" class="shrink-0" />
        <span :class="LABELS[variant]" class="text-neutral-900">{{ label }}</span>
        <span v-if="external && !ariaLabel" class="sr-only">, opens in a new tab</span>
    </a>
</template>
