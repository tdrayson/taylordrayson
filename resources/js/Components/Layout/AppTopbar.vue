<script setup>
import Breadcrumb from './Breadcrumb.vue';
import TimeJump from './TimeJump.vue';
import StatusBar from './StatusBar.vue';

defineProps({
    breadcrumb: { type: Array, default: undefined },
});
</script>

<template>
    <!-- Search lives in the sidebar (see AppSidebar.vue); the topbar carries
         wayfinding (breadcrumb) and ambient status only. -->
    <header class="hidden items-center gap-4 px-5 py-4 md:flex md:px-10">
        <div class="flex min-w-0 flex-1 items-center">
            <Breadcrumb :items="breadcrumb" />
        </div>
        <div class="flex items-center justify-end gap-4">
            <div class="hidden sm:block">
                <StatusBar />
            </div>
            <TimeJump />
        </div>
    </header>
</template>
