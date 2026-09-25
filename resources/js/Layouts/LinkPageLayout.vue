<script setup>
import { Head } from '@inertiajs/vue3';

defineProps({
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="min-h-dvh bg-neutral-0">
        <Head :title="title" />
        <main>
            <slot />
        </main>
    </div>
</template>
