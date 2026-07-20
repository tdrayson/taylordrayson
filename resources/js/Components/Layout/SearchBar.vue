<script setup>
import Icon from '../Ui/Icon.vue';
import { useCommandPalette } from '../../composables/useCommandPalette';

const { open } = useCommandPalette();

// Show the command glyph + K on Apple platforms, Ctrl K elsewhere.
const isApple = typeof navigator !== 'undefined' && /Mac|iPhone|iPad/.test(navigator.platform);
</script>

<template>
    <button
        type="button"
        aria-label="Open search"
        class="-ml-3 flex w-[calc(100%+0.75rem)] items-center gap-3 rounded-md px-3 py-2.5 text-base font-medium text-neutral-700 transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 md:py-2 md:text-sm"
        @click="open"
    >
        <Icon name="Search01Icon" class="size-5 flex-none text-neutral-500" />
        <span class="flex items-center gap-2">
            Search
            <kbd class="hidden items-center gap-0.5 text-meta font-normal text-neutral-500 md:inline-flex">
                <template v-if="isApple"><Icon name="CommandIcon" class="size-3.5" />K</template>
                <template v-else>Ctrl K</template>
            </kbd>
        </span>
    </button>
</template>
