<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';

const props = defineProps({
    // ISO date, 'YYYY-MM-DD'.
    date: { type: String, required: true },
    // The text to show (e.g. '3 February 2023').
    label: { type: String, required: true },
    // Link to the month page rather than the day page.
    month: { type: Boolean, default: false },
});

/**
 * The day (/YYYY/MM/DD) or month (/YYYY/MM) page URL for the date, so a reader
 * can jump to what was logged then.
 * @returns {string}
 */
const href = computed(() =>
    props.month ? `/${props.date.slice(0, 4)}/${props.date.slice(5, 7)}` : `/${props.date.replaceAll('-', '/')}`,
);
</script>

<template>
    <Link
        :href="href"
        class="underline decoration-neutral-300 decoration-1 underline-offset-2 transition-colors hover:text-neutral-900 hover:decoration-neutral-500 focus-visible:text-neutral-900 focus-visible:outline-none"
    >{{ label }}</Link>
</template>
