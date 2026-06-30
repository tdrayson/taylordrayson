<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    // Display name for the airline, e.g. 'easyJet'.
    name: { type: String, required: true },
    // Path to the small square airline icon, or null to show text only.
    icon: { type: String, default: null },
    // Filtered flights-by-airline URL.
    href: { type: String, required: true },
});
</script>

<template>
    <Link
        :href="href"
        class="story-airline inline-flex items-center gap-1.5 rounded-md bg-neutral-25 py-0.5 pl-1 pr-2 font-semibold text-neutral-900 no-underline transition-colors hover:bg-neutral-50 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-flight"
    >
        <img v-if="icon" :src="icon" alt="" class="size-5 shrink-0 rounded object-contain" />
        <span>{{ name }}</span>
    </Link>
</template>

<style scoped>
/* Seat the chip on the text baseline so it sits naturally mid-sentence. */
.story-airline {
    vertical-align: -0.35em;
}
</style>
