<script setup>
import MediaPlayer from '../Components/Overlays/MediaPlayer.vue';
import CommandPalette from '../Components/Overlays/CommandPalette.vue';
import SettingsModal from '../Components/Layout/SettingsModal.vue';

/**
 * The chrome-less shell, for a page that is a single full-viewport canvas.
 *
 * Everything AppLayout provides for wayfinding is deliberately absent, so the
 * page itself owns its title and its way back. The global overlays stay: the
 * command palette is the only navigation left, and a playing track should not
 * stop because the map opened.
 */
</script>

<template>
    <div data-canvas class="relative h-dvh w-full overflow-hidden">
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-neutral-900 focus:px-4 focus:py-2 focus:text-meta focus:font-medium focus:text-neutral-0 focus:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        >Skip to content</a>

        <main id="main-content" class="size-full">
            <slot />
        </main>

        <MediaPlayer />
        <CommandPalette />
        <SettingsModal />
    </div>
</template>
