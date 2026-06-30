<script setup>
import { computed } from 'vue';
import { InformationCircleIcon, CheckmarkCircle02Icon, Alert02Icon, CancelCircleIcon } from '@hugeicons-pro/core-stroke-rounded';
import { cn } from '../../lib/cn.js';
import Icon from './Icon.vue';

const props = defineProps({
    variant: { type: String, default: 'info' },
    title: { type: String, default: null },
    class: { type: [String, Array, Object], default: '' },
});

const VARIANTS = {
    info: { wrap: 'border-accent-500 bg-accent-50', icon: 'text-accent-500', glyph: InformationCircleIcon },
    success: { wrap: 'border-emerald-500 bg-emerald-50', icon: 'text-emerald-600', glyph: CheckmarkCircle02Icon },
    warning: { wrap: 'border-amber-500 bg-amber-50', icon: 'text-amber-600', glyph: Alert02Icon },
    danger: { wrap: 'border-red-500 bg-red-50', icon: 'text-red-600', glyph: CancelCircleIcon },
};

const tone = computed(() => VARIANTS[props.variant] ?? VARIANTS.info);

const role = computed(() => (props.variant === 'danger' || props.variant === 'warning' ? 'alert' : 'status'));
</script>

<template>
    <div :role="role" :class="cn('flex gap-3 rounded-lg border-l-2 px-4 py-3', tone.wrap, props.class)">
        <Icon :icon="tone.glyph" class="mt-0.5 size-5 shrink-0" :class="tone.icon" />
        <div class="min-w-0 text-meta text-neutral-700">
            <p v-if="title" class="mb-0.5 font-semibold text-neutral-900">{{ title }}</p>
            <slot />
        </div>
    </div>
</template>
