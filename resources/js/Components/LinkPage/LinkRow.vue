<script setup>
import { computed, useSlots } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import { cn } from '../../lib/cn.js';
import { SURFACE } from './shared.js';

const props = defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    description: { type: String, default: null },
    // A logo image path; otherwise `icon` is drawn in a tinted square, or the `media` slot wins.
    logo: { type: String, default: null },
    icon: { type: String, default: null },
    iconClass: { type: String, default: '' },
    // Internal rows are Inertia visits in the same tab; external ones open a new tab.
    internal: { type: Boolean, default: false },
    variant: { type: String, default: 'personal' },
});

const slots = useSlots();

const VARIANTS = {
    personal: { title: 'font-display text-lg font-bold', tint: 'bg-accent-50 text-accent-500' },
    business: { title: 'font-open-sans text-base font-semibold', tint: 'bg-tinker-50 text-tinker-600' },
};

const styles = computed(() => VARIANTS[props.variant] ?? VARIANTS.personal);

// Rows with no media at all (no slot, logo or icon) keep their text flush left.
const hasMedia = computed(() => Boolean(slots.media || props.logo || props.icon));
</script>

<template>
    <component
        :is="internal ? Link : 'a'"
        :href="href"
        :target="internal ? undefined : '_blank'"
        :rel="internal ? undefined : 'noopener noreferrer'"
        :class="cn('group relative flex items-center gap-4 rounded-lg p-4 transition-colors hover:border-neutral-200', SURFACE)"
    >
        <template v-if="hasMedia">
            <slot name="media">
                <img v-if="logo" :src="logo" alt="" class="size-11 shrink-0 rounded-md object-cover">
                <span v-else :class="styles.tint" class="flex size-11 shrink-0 items-center justify-center rounded-md">
                    <Icon :name="icon" :class="iconClass" class="size-5" />
                </span>
            </slot>
        </template>

        <span class="min-w-0 flex-1 pr-4">
            <span :class="styles.title" class="block leading-snug text-neutral-900">{{ label }}</span>
            <span v-if="description" class="mt-0.5 block text-sm text-neutral-500">{{ description }}</span>
        </span>

        <span v-if="!internal" class="sr-only">, opens in a new tab</span>
        <Icon
            name="ArrowUpRight01Icon"
            class="absolute right-3 top-3 size-3.5 text-neutral-400 transition-transform duration-150 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5 motion-reduce:transition-none"
        />
    </component>
</template>
