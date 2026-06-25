<script setup>
import { ArrowUpRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
});
</script>

<template>
    <a
        :href="href"
        target="_blank"
        rel="noopener noreferrer"
        :aria-label="`${label}, opens in a new tab`"
        class="inline-flex items-center gap-1.5 text-meta font-semibold text-neutral-700 transition-colors hover:text-accent-500"
    >
        {{ label }}
        <Icon :icon="ArrowUpRight01Icon" class="size-4" />
    </a>
</template>
