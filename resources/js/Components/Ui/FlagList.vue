<script setup>
import { computed } from 'vue';
import 'flag-icons/css/flag-icons.min.css';

const props = defineProps({
    // Two-letter ISO country codes, e.g. ['GB', 'US', 'FR'].
    codes: { type: Array, default: () => [] },
});

// Resolve full country names from codes in the browser (no SSR here).
const regionNames = typeof Intl !== 'undefined' && Intl.DisplayNames ? new Intl.DisplayNames(['en'], { type: 'region' }) : null;

/**
 * The display name for a country code, falling back to the code itself.
 * @param {string} code The ISO country code.
 * @returns {string}
 */
const name = (code) => {
    try {
        return regionNames?.of(code.toUpperCase()) ?? code;
    } catch {
        return code;
    }
};

// Codes ordered alphabetically by their resolved country name.
const sortedCodes = computed(() => [...props.codes].sort((a, b) => name(a).localeCompare(name(b))));
</script>

<template>
    <ul class="my-7 grid grid-cols-2 gap-x-6 gap-y-3 sm:grid-cols-3 lg:grid-cols-4">
        <li v-for="code in sortedCodes" :key="code" class="flex items-center gap-2.5 text-meta text-neutral-700">
            <span :class="['fi', `fi-${code.toLowerCase()}`]" class="story-flag aspect-4/3 shrink-0 rounded-xs inset-ring inset-ring-black/8" aria-hidden="true" />
            <span class="truncate">{{ name(code) }}</span>
        </li>
    </ul>
</template>

<style scoped>
/* Overrides flag-icons' unlayered .fi, which utilities can't; font-size 0 collapses its injected space. */
.story-flag {
    display: block;
    width: 1.375rem;
    font-size: 0;
    background-size: cover;
}
</style>
