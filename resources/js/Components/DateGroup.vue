<script setup>
import { Link } from '@inertiajs/vue3';
import TimelineFeed from './TimelineFeed.vue';

defineProps({
    label: { type: String, required: true },
    href: { type: String, default: null },
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <section>
        <h2 class="mb-6 font-display text-item-title">
            <Link v-if="href" :href="href" class="transition-colors hover:text-accent">{{ label }}</Link>
            <span v-else>{{ label }}</span>
        </h2>
        <TimelineFeed :items="items" />
    </section>
</template>
