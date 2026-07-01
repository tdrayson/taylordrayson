<script setup>
import { usePage } from '@inertiajs/vue3';
import { layoutProps } from '../composables/useLayout.js';
import AppSidebar from '../Components/Layout/AppSidebar.vue';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import MobileNav from '../Components/Layout/MobileNav.vue';
import MediaPlayer from '../Components/Overlays/MediaPlayer.vue';
import Breadcrumb from '../Components/Layout/Breadcrumb.vue';
import CommandPalette from '../Components/Overlays/CommandPalette.vue';

const page = usePage();
</script>

<template>
    <div class="flex min-h-dvh flex-col md:flex-row">
        <AppSidebar />
        <main class="flex min-w-0 flex-1 flex-col">
            <MobileNav />
            <div class="px-5 py-3 md:hidden">
                <Breadcrumb :items="layoutProps.breadcrumb" />
            </div>
            <AppTopbar :breadcrumb="layoutProps.breadcrumb" />
            <div :key="page.url" class="content-grid w-full animate-fade-in pb-28 pt-8">
                <slot />
            </div>
        </main>
        <MediaPlayer />
        <CommandPalette />
    </div>
</template>
