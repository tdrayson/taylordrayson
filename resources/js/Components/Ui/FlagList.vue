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
            <span :class="['fi', `fi-${code.toLowerCase()}`]" class="story-flag shrink-0" aria-hidden="true" />
            <span class="truncate">{{ name(code) }}</span>
        </li>
    </ul>
</template>

<style scoped>
/* Fixed 4:3 flag boxes so every flag matches and the grid columns line up.
   font-size: 0 cancels the &nbsp; flag-icons injects for height; a hairline
   ring defines pale flags against the page. */
.story-flag {
    display: block;
    width: 1.375rem;
    height: 1.03125rem;
    font-size: 0;
    background-size: cover;
    background-position: center;
    border-radius: 2px;
    box-shadow: inset 0 0 0 1px rgb(0 0 0 / 0.08);
}
</style>
