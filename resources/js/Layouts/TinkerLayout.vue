<script setup>
import '@fontsource-variable/montserrat';
import '@fontsource-variable/open-sans';
import '@fontsource/bebas-neue';
import { Head } from '@inertiajs/vue3';

// The business card's own brand faces, imported here so only its pages download them.
defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="min-h-dvh bg-neutral-25 font-open-sans dark:bg-neutral-0">
        <Head :title="title" />
        <main>
            <slot />
        </main>
    </div>
</template>
