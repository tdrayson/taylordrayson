<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import FeedRail from '../Components/Timeline/FeedRail.vue';
import TripCard from '../Components/Trips/TripCard.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    // [{ title, href, days, start, end, year }], newest first from the server.
    // Tags are deliberately absent: they belong to the trip page.
    trips: { type: Array, default: () => [] },
});

setLayoutProps({ breadcrumb: [{ label: 'Trips' }] });

// Trips bucketed under their start year, preserving the server's newest-first
// order within each bucket, so the index reads like the timeline's year runs.
const byYear = computed(() => {
    const years = new Map();

    for (const trip of props.trips) {
        if (! years.has(trip.year)) {
            years.set(trip.year, []);
        }

        years.get(trip.year).push(trip);
    }

    return [...years.entries()].map(([year, trips]) => ({ year, trips }));
});
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Trips</h1>
    </header>

    <div v-if="trips.length" class="mt-10 flex flex-col gap-14">
        <section v-for="group in byYear" :key="group.year">
            <h2 class="mb-6 font-display text-item-title">{{ group.year }}</h2>

            <FeedRail>
                <TripCard
                    v-for="trip in group.trips"
                    :key="trip.href"
                    :title="trip.title"
                    :href="trip.href"
                    :start="trip.start"
                    :end="trip.end"
                    :days="trip.days"
                />
            </FeedRail>
        </section>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">No trips yet.</p>
</template>
