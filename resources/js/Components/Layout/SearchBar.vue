<script setup>
import { computed } from 'vue';
import { Search01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import { useCommandPalette } from '../../composables/useCommandPalette';

const { open } = useCommandPalette();

// Show ⌘K on Apple platforms, Ctrl K elsewhere.
const isApple = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const shortcut = computed(() => (isApple ? '⌘K' : 'Ctrl K'));
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-2.5 rounded-full border border-neutral-50 bg-neutral-25 px-4 py-2 text-neutral-500 transition-colors hover:border-neutral-100 focus-visible:border-accent-500 focus-visible:outline-none"
        @click="open"
    >
        <Icon :icon="Search01Icon" class="size-4 shrink-0" />
        <span class="text-nav text-neutral-700">Search</span>
        <kbd class="ml-auto rounded border border-neutral-50 bg-neutral-0 px-1.5 py-0.5 text-label text-neutral-500">{{ shortcut }}</kbd>
    </button>
</template>
