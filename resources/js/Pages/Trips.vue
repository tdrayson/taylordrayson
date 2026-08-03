<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    // [{ title, href, days, start, end, year, tags }], newest first from the server.
    trips: { type: Array, default: () => [] },
});

setLayoutProps({ breadcrumb: [{ label: 'Trips' }] });

// Trips bucketed under their start year, preserving the server's newest-first
// order within each bucket.
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
        <p v-if="trips.length" class="mt-2 text-meta text-neutral-500">
            {{ trips.length }} {{ trips.length === 1 ? 'trip' : 'trips' }}, newest first
        </p>
    </header>

    <div v-if="trips.length" class="mt-10 flex flex-col gap-12">
        <section v-for="group in byYear" :key="group.year">
            <h2 class="text-label font-semibold uppercase tracking-wide text-neutral-400">{{ group.year }}</h2>

            <ul class="mt-4 flex flex-col divide-y divide-neutral-50 border-t border-neutral-50">
                <li v-for="trip in group.trips" :key="trip.href">
                    <Link
                        :href="trip.href"
                        class="flex flex-col gap-1 py-4 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >
                        <span class="font-display text-name font-semibold text-neutral-800">{{ trip.title }}</span>
                        <span class="text-meta text-neutral-500">
                            {{ trip.start === trip.end ? trip.start : `${trip.start} to ${trip.end}` }}
                            <span class="tabular-nums text-neutral-400">
                                {{ trip.days }} {{ trip.days === 1 ? 'day' : 'days' }}
                            </span>
                        </span>
                        <span v-if="trip.tags.length" class="text-label text-neutral-400">
                            {{ trip.tags.map((tag) => tag.name).join(', ') }}
                        </span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">No trips yet.</p>
</template>
