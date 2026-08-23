<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import FlightMapStats from './FlightMapStats.vue';
import FlightMapYears from './FlightMapYears.vue';
import FlightMapList from './FlightMapList.vue';

/**
 * The flight globe map's sidebar panel: title, year filter, stats, and the
 * flight list. Positioning (floating overlay on desktop, bottom sheet on
 * mobile) is the page's responsibility; this component is just the panel
 * content.
 *
 * The map page runs without the app shell, so the title here is the page's
 * only heading and the back link its only way out.
 *
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
    <!-- One scroll area on a phone, where the header alone is taller than the
         sheet; a fixed header over a scrolling list once there is room for it. -->
    <div class="flex max-h-full flex-col overflow-y-auto rounded-lg border border-neutral-50 bg-neutral-0 shadow-card md:overflow-hidden">
        <!-- A tonal step, not a rule: the tinted header reads as its own band
             against the white list, and the panel keeps a single border. -->
        <div class="shrink-0 space-y-4 bg-neutral-25 p-4">
            <div class="flex items-center gap-2">
                <Link
                    href="/flights"
                    class="-ml-1 flex size-7 shrink-0 items-center justify-center rounded-full text-neutral-500 transition-colors hover:bg-neutral-25 hover:text-neutral-900 focus-visible:bg-neutral-25 focus-visible:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    aria-label="Back to flights"
                >
                    <Icon name="ArrowLeft01Icon" class="size-4" />
                </Link>
                <h1 class="font-display text-section text-neutral-900">Flight map</h1>
            </div>

            <FlightMapYears :years="years" :model-value="year" @update:model-value="$emit('update:year', $event)" />
            <FlightMapStats :stats="stats" />
        </div>
        <FlightMapList
            class="md:min-h-0 md:flex-1 md:overflow-y-auto"
            :entries="entries"
            :selected-id="selectedId"
            @select="$emit('select', $event)"
            @hover="$emit('hover', $event)"
        />
    </div>
</template>
