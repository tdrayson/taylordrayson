<script setup>
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../Components/AppSidebar.vue';
import AppTopbar from '../Components/AppTopbar.vue';
import MobileNav from '../Components/MobileNav.vue';
import MediaPlayer from '../Components/MediaPlayer.vue';
import Breadcrumb from '../Components/Breadcrumb.vue';
import CommandPalette from '../Components/CommandPalette.vue';

defineProps({
    breadcrumb: { type: Array, default: undefined },
});

const page = usePage();
</script>

<template>
    <div class="flex min-h-screen flex-col md:flex-row">
        <AppSidebar />
        <main class="flex min-w-0 flex-1 flex-col">
            <MobileNav />
            <div v-if="breadcrumb" class="px-5 py-3 md:hidden">
                <Breadcrumb :items="breadcrumb" />
            </div>
            <AppTopbar :breadcrumb="breadcrumb" />
            <div :key="page.url" class="content-grid w-full animate-fade-in pb-28 pt-8">
                <slot />
            </div>
        </main>
        <MediaPlayer />
        <CommandPalette />
    </div>
</template>
