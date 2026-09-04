<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import StyledSelect from '../Search/StyledSelect.vue';

/**
 * Year shortcut for the timeline's pagination bar. The feed runs to hundreds of
 * pages, so the way out of it is a date, not a page number: picking a year goes
 * to the archive that already exists at /{year}.
 *
 * A select rather than a row of links, because the site reaches back to 2003 and
 * twenty-odd inline years wrap into a second line of mostly-empty years.
 */
const props = defineProps({
    // list<{ year: Number, href: String }>, newest first.
    years: { type: Array, default: () => [] },
    // Preselected as where the visible page sits, when it sits in one year.
    current: { type: Number, default: null },
});

const options = computed(() => props.years.map((entry) => ({ value: entry.year, label: String(entry.year) })));

// The select holds a year; the router needs the href that went with it.
function go(year) {
    const match = props.years.find((entry) => String(entry.year) === String(year));

    if (match) {
        router.visit(match.href);
    }
}
</script>

<template>
    <div v-if="years.length" class="mt-4 flex items-center justify-center gap-3">
        <label for="year-jump" class="text-meta text-neutral-500">Jump to</label>

        <StyledSelect
            id="year-jump"
            class="w-32"
            :model-value="current ?? ''"
            :options="options"
            placeholder="Year"
            @update:model-value="go"
        />
    </div>
</template>
