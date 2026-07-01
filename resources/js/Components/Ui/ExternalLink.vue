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
        class="group inline-flex items-center gap-1.5 rounded-sm text-meta font-semibold text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
    >
        {{ label }}
        <Icon
            :icon="ArrowUpRight01Icon"
            class="size-4 transition-transform duration-150 ease-out group-hover:-translate-y-0.5 group-hover:translate-x-0.5 group-focus-visible:-translate-y-0.5 group-focus-visible:translate-x-0.5 motion-reduce:transition-none"
        />
    </a>
</template>
