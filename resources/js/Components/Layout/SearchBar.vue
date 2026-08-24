<script setup>
import { ref, onMounted } from 'vue';
import Icon from '../Ui/Icon.vue';
import { useCommandPalette } from '../../composables/useCommandPalette';

const { open } = useCommandPalette();

// Show the command glyph + K on Apple platforms, Ctrl K elsewhere.
//
// Resolved after mount, not during setup: there is no `navigator` while the
// page is server-rendered, so reading it here made the client's first render
// disagree with the server's and fail hydration on every Mac. Starting false
// matches what the server sent, and the glyph swaps in once hydration is done.
const isApple = ref(false);

onMounted(() => {
    // userAgentData is the supported replacement for the deprecated
    // navigator.platform, which is absent in Safari and Firefox.
    const platform = navigator.userAgentData?.platform ?? navigator.platform ?? '';

    isApple.value = /mac|iphone|ipad|ios/i.test(platform);
});
</script>

<template>
    <button
        type="button"
        aria-label="Open search"
        class="-ml-3 flex w-[calc(100%+0.75rem)] items-center gap-3 rounded-md px-3 py-2.5 text-base font-medium text-neutral-700 transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 md:py-2 md:text-sm"
        @click="open"
    >
        <Icon name="Search01Icon" class="size-5 flex-none text-neutral-500" />
        Search
        <kbd class="ml-auto hidden items-center gap-0.5 text-meta font-normal text-neutral-500 md:inline-flex">
            <template v-if="isApple"><Icon name="CommandIcon" class="size-3.5" />K</template>
            <template v-else>Ctrl K</template>
        </kbd>
    </button>
</template>
