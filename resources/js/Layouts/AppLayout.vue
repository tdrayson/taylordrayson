<script setup>
import { usePage } from '@inertiajs/vue3';
import AppSidebar from '../Components/Layout/AppSidebar.vue';
import AppTopbar from '../Components/Layout/AppTopbar.vue';
import MobileNav from '../Components/Layout/MobileNav.vue';
import MediaPlayer from '../Components/Overlays/MediaPlayer.vue';
import Breadcrumb from '../Components/Layout/Breadcrumb.vue';
import CommandPalette from '../Components/Overlays/CommandPalette.vue';

defineProps({
    breadcrumb: { type: Array, default: () => [] },
    mode: { type: String, default: 'public' },
});

const page = usePage();
</script>

<template>
    <div class="flex min-h-dvh flex-col md:flex-row">
        <AppSidebar :mode="mode" />
        <main class="flex min-w-0 flex-1 flex-col" :class="mode === 'cp' ? 'bg-neutral-25' : ''">
            <MobileNav />
            <div class="px-5 py-3 md:hidden">
                <Breadcrumb :items="breadcrumb" />
            </div>
            <AppTopbar :breadcrumb="breadcrumb" :mode="mode" />
            <div :key="page.url" class="content-grid w-full animate-fade-in pb-28 pt-8">
                <slot />
            </div>
        </main>
        <MediaPlayer v-if="mode !== 'cp'" />
        <CommandPalette v-if="mode !== 'cp'" />
    </div>
</template>
