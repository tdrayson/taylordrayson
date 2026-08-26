<script setup>
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../Components/Layout/AppSidebar.vue';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import MobileNav from '../Components/Layout/MobileNav.vue';
import MediaPlayer from '../Components/Overlays/MediaPlayer.vue';
import Breadcrumb from '../Components/Layout/Breadcrumb.vue';
import CommandPalette from '../Components/Overlays/CommandPalette.vue';
import SettingsModal from '../Components/Layout/SettingsModal.vue';
import QuickAdd from '../Components/Layout/QuickAdd.vue';

defineProps({
    breadcrumb: { type: Array, default: () => [] },
    // Writing screens drop the ambient chrome: wayfinding and identity stay,
    // the status readings and the menu do not.
    minimal: { type: Boolean, default: false },
});

const page = usePage();
</script>

<template>
    <div class="flex min-h-dvh flex-col md:flex-row">
        <!-- First focusable element on every page: lets keyboard/screen-reader
             users jump straight past the sidebar/mobile nav to the page content. -->
        <a
            href="#main-content"
            class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-md focus:bg-neutral-900 focus:px-4 focus:py-2 focus:text-meta focus:font-medium focus:text-neutral-0 focus:shadow-card focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        >Skip to content</a>
        <AppSidebar />
        <div class="flex min-w-0 flex-1 flex-col">
            <!-- Single banner: mobile bar and desktop topbar share one <header> outside <main>. -->
            <header>
                <MobileNav :minimal="minimal" />
                <div class="px-5 py-3 md:hidden">
                    <Breadcrumb :items="breadcrumb" />
                </div>
                <AppTopbar :breadcrumb="breadcrumb" :minimal="minimal" />
            </header>
            <main id="main-content" class="flex min-w-0 flex-1 flex-col">
                <div :key="page.url" class="content-grid w-full animate-fade-in pb-28 pt-8">
                    <slot />
                </div>
            </main>
        </div>
        <MediaPlayer />
        <CommandPalette />
        <SettingsModal />
        <!-- Off the writing screens: they carry their own Post button on the
             same edge, and there is nothing to quick-add from inside a form. -->
        <QuickAdd v-if="! minimal" />
    </div>
</template>
