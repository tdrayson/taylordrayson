<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import { useFormat } from '../../composables/useFormat';
import { duration, dateLong, titleCase } from '../../lib/format.js';

/**
 * One row in the flight globe map's list: a keyboard-reachable button so the
 * list is a fully accessible alternative to hovering/clicking the globe.
 * Selecting the row reveals a detail panel below it, kept OUTSIDE the button
 * (a sibling, not a descendant) because it holds a Link, and an <a> nested
 * inside a <button> is invalid HTML and breaks keyboard/AT behaviour.
 * @param {object} entry A FlightMapEntry payload.
 * @param {boolean} selected Whether this flight is the current selection.
 */
const props = defineProps({
    entry: { type: Object, required: true },
    selected: { type: Boolean, default: false },
});

const emit = defineEmits(['select', 'hover']);

const { distance } = useFormat();

const airline = computed(() => props.entry.airline);

// Origin and destination place names, joined so either can be missing without leaving a stray connective.
const places = computed(() => [props.entry.origin.place, props.entry.destination.place].filter(Boolean).join(' to '));

const dateLabel = computed(() => dateLong(props.entry.occurredAt));

const distanceLabel = computed(() => distance(props.entry.distance));
const durationLabel = computed(() => duration(props.entry.duration));

const metrics = computed(() => [distanceLabel.value, durationLabel.value].filter(Boolean).join(', '));

// airline.number already carries the flight designator; entry.flightNumber
// is the fallback for the (unrelated) case where the flight has no matched airline record.
const flightNumberLabel = computed(() => airline.value?.number ?? props.entry.flightNumber);

const aircraftLabel = computed(() => props.entry.aircraft);
const cabinLabel = computed(() => (props.entry.cabinClass ? titleCase(props.entry.cabinClass) : null));
</script>

<template>
    <div>
        <button
            type="button"
            class="w-full px-4 py-3 text-left transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500"
            :class="selected ? 'bg-accent-50' : ''"
            :aria-expanded="selected"
            @click="emit('select', entry.id)"
            @mouseenter="emit('hover', entry.id)"
            @focus="emit('hover', entry.id)"
            @mouseleave="emit('hover', null)"
            @blur="emit('hover', null)"
        >
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-1.5 text-meta font-semibold text-neutral-900 tnum">
                    <span>{{ entry.origin.iata }}</span>
                    <Icon name="ArrowRight01Icon" class="size-3.5 text-neutral-400" />
                    <span>{{ entry.destination.iata }}</span>
                </div>
                <span class="text-label text-neutral-500 tnum">{{ metrics }}</span>
            </div>
            <div class="mt-1 flex items-center justify-between gap-3 text-label text-neutral-500">
                <span v-if="places">{{ places }}</span>
                <span class="tnum">{{ dateLabel }}</span>
            </div>
            <div v-if="airline || flightNumberLabel" class="mt-1.5 flex items-center gap-1.5">
                <img v-if="airline?.icon" :src="airline.icon" :alt="airline.name || 'Airline logo'" class="h-4 w-auto object-contain">
                <span v-else-if="airline?.name" class="text-label text-neutral-500">{{ airline.name }}</span>
                <span class="text-label text-neutral-500 tnum">{{ flightNumberLabel }}</span>
            </div>
        </button>

        <div v-if="selected" class="space-y-1.5 border-t border-neutral-50 bg-accent-50 px-4 py-3 text-label text-neutral-500">
            <div v-if="aircraftLabel" class="flex items-center justify-between gap-3">
                <span class="uppercase text-neutral-400">Aircraft</span>
                <span class="text-neutral-700">{{ aircraftLabel }}</span>
            </div>
            <div v-if="cabinLabel" class="flex items-center justify-between gap-3">
                <span class="uppercase text-neutral-400">Cabin</span>
                <span class="text-neutral-700">{{ cabinLabel }}</span>
            </div>
            <div v-if="durationLabel" class="flex items-center justify-between gap-3">
                <span class="uppercase text-neutral-400">Duration</span>
                <span class="text-neutral-700 tnum">{{ durationLabel }}</span>
            </div>
            <Link
                :href="entry.href"
                class="inline-block pt-1 font-semibold text-accent-700 underline-offset-2 hover:underline focus-visible:underline focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
                View flight
            </Link>
        </div>
    </div>
</template>
