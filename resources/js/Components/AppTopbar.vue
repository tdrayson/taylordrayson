<script setup>
import Breadcrumb from './Breadcrumb.vue';
import TimeJump from './TimeJump.vue';
import StatusBar from './StatusBar.vue';

defineProps({
    breadcrumb: { type: Array, default: undefined },
});
</script>

<template>
    <header class="hidden items-center gap-4 px-5 py-4 md:flex md:px-10">
        <Breadcrumb :items="breadcrumb" />
        <div class="ml-auto flex items-center gap-4">
            <div class="hidden sm:block">
                <StatusBar />
            </div>
            <TimeJump />
        </div>
    </header>
</template>
