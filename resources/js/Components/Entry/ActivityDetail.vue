<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import SectionHead from '../Ui/SectionHead.vue';
import HeartRateChart from '../Stats/HeartRateChart.vue';
import { number, titleCase } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

// Stored series is a list of { time, bpm } points; the chart wants bare BPM values.
const heartRate = computed(() => {
    const series = props.entry.heart_rate;

    if (!Array.isArray(series) || series.length === 0) {
        return [];
    }

    return series.map((point) => (typeof point === 'number' ? point : point.bpm));
});

/** Real data uses weight_kg; the factory/parser use weight. Support both. */
function setWeight(set) {
    return Number(set.weight_kg ?? set.weight ?? 0);
}

const stats = computed(() => [
    { label: 'Distance', value: number(props.entry.distance_km, 2), unit: 'km' },
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
    return value > 0 ? `${number(value, value % 1 ? 1 : 0)} kg` : 'Bodyweight';
}
</script>

<template>
    <div class="space-y-8">
        <StatGrid :stats="stats" />

        <div v-if="!exercises.length && heartRate.length">
            <SectionHead title="Heart rate" meta="bpm over the activity" />
            <HeartRateChart :data="heartRate" :duration="entry.duration" />
        </div>

        <div v-if="exercises.length">
            <SectionHead title="Exercises" :meta="totalVolume ? `${number(totalVolume)} kg volume` : ''" />
            <div class="space-y-3">
                <div v-for="exercise in exercises" :key="exercise.name" class="overflow-hidden rounded-lg border border-neutral-50">
                    <div class="flex items-baseline justify-between gap-4 bg-neutral-25 px-4 py-2.5">
                        <span class="min-w-0 truncate font-display text-section">{{ exercise.name }}</span>
                        <span class="shrink-0 text-meta font-semibold text-neutral-700 tnum">
                            {{ exercise.sets.length }} {{ exercise.sets.length === 1 ? 'set' : 'sets' }}<template v-if="exercise.volume"> · {{ number(exercise.volume) }} kg</template>
                        </span>
                    </div>
                    <div class="divide-y divide-neutral-50">
                        <div v-for="(set, index) in exercise.sets" :key="index" class="flex items-center justify-between gap-4 px-4 py-2">
                            <span class="text-label uppercase text-neutral-500">Set {{ index + 1 }}</span>
                            <span class="text-meta text-neutral-900 tnum">
                                <span class="font-semibold">{{ set.reps }}</span> <span class="text-neutral-500">reps</span> · {{ weightLabel(set.weight) }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
