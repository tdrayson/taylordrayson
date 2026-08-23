<script setup>
import FlightMapStats from './FlightMapStats.vue';
import FlightMapYears from './FlightMapYears.vue';
import FlightMapList from './FlightMapList.vue';

/**
 * The flight globe map's sidebar panel: year filter, stats, and the flight
 * list. Positioning (floating overlay on desktop, bottom sheet on mobile) is
 * the page's responsibility; this component is just the panel content.
 * @param {Array} entries FlightMapEntry payloads for the current year filter.
 * @param {Array<number>} years Years present in the full dataset.
 * @param {object} stats The FlightMapStats payload for the current year filter.
 * @param {number|null} year Selected year, or null for all time.
 * @param {number|null} selectedId Currently selected flight id.
 */
defineProps({
    entries: { type: Array, default: () => [] },
    years: { type: Array, default: () => [] },
    stats: { type: Object, required: true },
    year: { type: Number, default: null },
    selectedId: { type: Number, default: null },
});

defineEmits(['update:year', 'select', 'hover']);
</script>

<template>
    <div class="flex max-h-full flex-col overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0 shadow-card">
        <div class="space-y-4 border-b border-neutral-50 p-4">
            <FlightMapYears :years="years" :model-value="year" @update:model-value="$emit('update:year', $event)" />
            <FlightMapStats :stats="stats" />
        </div>
        <FlightMapList
            class="min-h-0 flex-1 overflow-y-auto"
            :entries="entries"
            :selected-id="selectedId"
            @select="$emit('select', $event)"
            @hover="$emit('hover', $event)"
        />
    </div>
</template>
