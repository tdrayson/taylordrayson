<script setup>
import { computed } from 'vue';
import { Search01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { useCommandPalette } from '../composables/useCommandPalette';

const { open } = useCommandPalette();

// Show ⌘K on Apple platforms, Ctrl K elsewhere.
const isApple = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
const shortcut = computed(() => (isApple ? '⌘K' : 'Ctrl K'));
</script>

<template>
    <button
        type="button"
        class="flex w-full items-center gap-2.5 rounded-full border border-line-2 bg-surface px-4 py-2 text-ink-3 transition-colors hover:border-line focus-visible:border-accent focus-visible:outline-none"
        @click="open"
    >
        <Icon :icon="Search01Icon" class="size-4 shrink-0" />
        <span class="text-nav text-ink-2">Search</span>
        <kbd class="ml-auto rounded border border-line-2 bg-canvas px-1.5 py-0.5 text-label text-ink-3">{{ shortcut }}</kbd>
    </button>
</template>
