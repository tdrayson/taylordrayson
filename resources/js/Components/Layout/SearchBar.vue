<script setup>
import { Search01Icon, CommandIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import { useCommandPalette } from '../../composables/useCommandPalette';

const { open } = useCommandPalette();

// Show the command glyph + K on Apple platforms, Ctrl K elsewhere.
const isApple = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
</script>

<template>
    <button
        type="button"
        class="flex h-9 w-full items-center gap-2.5 rounded-full border border-neutral-50 bg-neutral-25 px-4 text-neutral-500 transition-colors hover:border-neutral-100 focus-visible:border-accent-500 focus-visible:outline-none"
        @click="open"
    >
        <Icon :icon="Search01Icon" class="size-4 shrink-0" />
        <span class="text-nav text-neutral-700">Search</span>
        <kbd class="ml-auto flex items-center gap-0.5 rounded border border-neutral-50 bg-neutral-0 px-1.5 py-0.5 text-meta text-neutral-500">
            <template v-if="isApple"><Icon :icon="CommandIcon" class="size-3.5" />K</template>
            <template v-else>Ctrl K</template>
        </kbd>
    </button>
</template>
