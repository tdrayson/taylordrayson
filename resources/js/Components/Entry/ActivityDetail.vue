<script setup>
import { computed, ref } from 'vue';
import { Deferred, usePage } from '@inertiajs/vue3';
import StatGrid from '../Stats/StatGrid.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ActivityProfile from './ActivityProfile.vue';
import ActivityMedia from './ActivityMedia.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import { number, titleCase } from '../../lib/format.js';
import { useFormat } from '../../composables/useFormat';
import { useActivityCursor } from '../../composables/useActivityCursor';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Unit-aware distance/weight formatters; reading their settings reactively
// keeps stats/labels live when a visitor toggles units in Settings.
const { distanceParts, weight } = useFormat();

const photos = computed(() => (Array.isArray(props.entry.photos) ? props.entry.photos : []));
const polyline = computed(() => props.entry.meta?.polyline ?? null);
const lightboxIndex = ref(null);

// Stream series (heart_rate/altitude/speed/track) are deferred separately
// from the main entry payload; read them once Inertia fetches the prop.
const page = usePage();
const profile = computed(() => page.props.profile);

// Shared cursor across the profile charts (and the route map, in a later
// task) so hovering one series highlights the same point everywhere.
const cursor = useActivityCursor();

/** Real data uses weight_kg; the factory/parser use weight. Support both. */
function setWeight(set) {
    return Number(set.weight_kg ?? set.weight ?? 0);
}

const stats = computed(() => [
    { label: 'Distance', ...(distanceParts(props.entry.distance, 1) ?? { value: null, unit: '' }) },
    { label: 'Duration', seconds: props.entry.duration ?? null },
    { label: 'Calories', value: number(props.entry.calories), unit: 'kcal' },
    { label: 'Avg HR', value: number(props.entry.average_heart_rate), unit: 'bpm' },
    { label: 'Max HR', value: number(props.entry.max_heart_rate), unit: 'bpm' },
    { label: 'Elevation', value: number(props.entry.meta?.elevation_gain), unit: 'm' },
]);

const exercises = computed(() => {
    const sets = props.entry.meta?.sets;

    if (!Array.isArray(sets) || sets.length === 0) {
        return [];
    }

    const grouped = [];

    sets.forEach((set) => {
        const name = titleCase(set.exercise);
        let group = grouped.find((item) => item.name === name);

        if (!group) {
            group = { name, sets: [], volume: 0 };
            grouped.push(group);
        }

        const weight = setWeight(set);
        group.sets.push({ reps: set.reps, weight });
        group.volume += (set.reps || 0) * weight;
    });

    return grouped;
});

const totalVolume = computed(() => exercises.value.reduce((sum, exercise) => sum + exercise.volume, 0));

function weightLabel(value) {
    return value > 0 ? weight(value) : 'Bodyweight';
}
</script>

<template>
    <div class="space-y-8">
        <p v-if="entry.description" class="text-balance whitespace-pre-line text-neutral-700">
            {{ entry.description }}
        </p>

        <StatGrid :stats="stats" />

        <ActivityMedia
            v-if="polyline || photos.length"
            :polyline="polyline"
            :photos="photos"
            color="var(--color-activity)"
            @open="lightboxIndex = $event"
        />

        <Lightbox v-model:index="lightboxIndex" :photos="photos" />

        <Deferred v-if="!exercises.length" data="profile">
            <template #fallback>
                <div class="h-40 w-full animate-pulse rounded-lg bg-neutral-25" />
            </template>
            <ActivityProfile v-if="profile" :profile="profile" :duration="entry.duration" :cursor="cursor" />
        </Deferred>

        <div v-if="exercises.length">
            <SectionHead title="Exercises" :meta="totalVolume ? `${weight(totalVolume, 0)} volume` : ''" />
            <div class="space-y-3">
                <div v-for="exercise in exercises" :key="exercise.name" class="overflow-hidden rounded-lg border border-neutral-50">
                    <div class="flex items-baseline justify-between gap-4 bg-neutral-25 px-4 py-2.5">
                        <span class="min-w-0 truncate font-display text-section">{{ exercise.name }}</span>
                        <span class="shrink-0 text-meta font-semibold text-neutral-700 tnum">
                            {{ exercise.sets.length }} {{ exercise.sets.length === 1 ? 'set' : 'sets' }}<template v-if="exercise.volume">, {{ weight(exercise.volume, 0) }}</template>
                        </span>
                    </div>
                    <div class="divide-y divide-neutral-50">
                        <div v-for="(set, index) in exercise.sets" :key="index" class="flex items-center justify-between gap-4 px-4 py-2">
                            <span class="text-label uppercase text-neutral-500">Set {{ index + 1 }}</span>
                            <span class="text-meta text-neutral-900 tnum">
                                <span class="font-semibold">{{ set.reps }}</span> <span class="text-neutral-500">reps</span>, {{ weightLabel(set.weight) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
