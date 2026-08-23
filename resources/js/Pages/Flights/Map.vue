<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import FlightGlobe from '../../Components/Maps/FlightGlobe.vue';
import FlightMapSidebar from '../../Components/Flights/FlightMapSidebar.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    entries: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
});

setLayoutProps({
    fullscreen: true,
    breadcrumb: [
        { label: 'Flights', href: '/flights' },
        { label: 'Map' },
    ],
});

// null means all time; the globe re-fits its own camera when this changes.
const year = ref(null);
const selectedId = ref(null);
const hoveredId = ref(null);

/** Entries for the selected year, or every entry for all time. */
const visible = computed(() => (year.value === null
    ? props.entries
    : props.entries.filter((entry) => entry.year === year.value)));

/** Stats are precomputed per bucket server-side, so switching years is a lookup. */
const stats = computed(() => props.stats[year.value === null ? 'all' : String(year.value)]);

/** Toggle a flight's selection: picking the already-selected id clears it. */
function toggleSelected(id) {
    selectedId.value = selectedId.value === id ? null : id;
}

function handleYearChange(newYear) {
    year.value = newYear;
    // A selection from the old bucket may not exist in the new one.
    selectedId.value = null;
}

function handleEscape(event) {
    if (event.key === 'Escape') {
        selectedId.value = null;
    }
}

onMounted(() => document.addEventListener('keydown', handleEscape));
onBeforeUnmount(() => document.removeEventListener('keydown', handleEscape));
</script>

<template>
    <div class="full-width relative">
        <AppHead :og="{ title: 'Flights map' }" />

        <FlightGlobe
            class="absolute inset-0"
            :entries="visible"
            :year="year"
            :selected-id="selectedId"
            :hovered-id="hoveredId"
            @select="toggleSelected"
        />

        <div class="absolute inset-x-4 bottom-4 z-10 max-h-80 md:inset-x-auto md:bottom-4 md:left-4 md:top-4 md:max-h-none md:w-80">
            <FlightMapSidebar
                :entries="visible"
                :years="years"
                :stats="stats"
                :year="year"
                :selected-id="selectedId"
                @update:year="handleYearChange"
                @select="toggleSelected"
                @hover="hoveredId = $event"
            />
        </div>
    </div>
</template>
