<script setup>
import { computed } from 'vue';
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/ViewHeader.vue';
import StatRow from '../Components/StatRow.vue';
import ActivityRings from '../Components/ActivityRings.vue';
import TimelineFeed from '../Components/TimelineFeed.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    day: { type: Number, required: true },
    items: { type: Array, default: () => [] },
    stats: { type: Array, default: () => [] },
    rings: { type: Object, default: null },
    steps: { type: [String, Number], default: null },
});

// Steps sit alongside the entry-derived stats in the same row.
const summaryStats = computed(() => [
    ...props.stats,
    ...(props.steps !== null ? [{ label: 'Steps', value: Number(props.steps).toLocaleString('en-GB') }] : []),
]);

const pad = (value) => String(value).padStart(2, '0');
const date = computed(() => new Date(props.year, props.month - 1, props.day));
const monthName = computed(() => date.value.toLocaleDateString('en-GB', { month: 'long' }));
const weekday = computed(() => date.value.toLocaleDateString('en-GB', { weekday: 'long' }));

const title = computed(() => `${weekday.value}<br>${props.day} ${monthName.value} ${props.year}`);
const subtitle = computed(() => `${props.items.length} ${props.items.length === 1 ? 'entry' : 'entries'} logged`);

function dayUrl(value) {
    return `/${value.getFullYear()}/${pad(value.getMonth() + 1)}/${pad(value.getDate())}`;
}

function shortLabel(value) {
    return value.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric' });
}

const prevDate = computed(() => {
    const value = new Date(date.value);
    value.setDate(value.getDate() - 1);
    return value;
});

const nextDate = computed(() => {
    const value = new Date(date.value);
    value.setDate(value.getDate() + 1);
    return value;
});

setLayoutProps({
    breadcrumb: [
        { label: String(props.year), href: `/${props.year}` },
        { label: monthName.value, href: `/${props.year}/${pad(props.month)}` },
        { label: String(props.day) },
    ],
});
</script>

<template>
    <Head :title="`${day} ${monthName} ${year}`" />

    <ViewHeader
        :title="title"
        :subtitle="subtitle"
        :prev="{ label: shortLabel(prevDate), href: dayUrl(prevDate) }"
        :next="{ label: shortLabel(nextDate), href: dayUrl(nextDate) }"
    />

    <div v-if="rings" class="mt-8 flex items-center gap-5">
        <ActivityRings large :move="rings.move" :exercise="rings.exercise" :stand="rings.stand" />
        <div class="space-y-1.5 text-sm text-ink-3">
            <div><span class="font-display text-base font-bold text-ink tnum">{{ rings.moveKcal }}</span> kcal move</div>
            <div><span class="font-display text-base font-bold text-ink tnum">{{ rings.exerciseMins }}</span> min exercise</div>
            <div><span class="font-display text-base font-bold text-ink tnum">{{ rings.standHrs }}</span> hr stand</div>
        </div>
    </div>

    <StatRow v-if="summaryStats.length" :stats="summaryStats" class="mt-8" />

    <TimelineFeed v-if="items.length" :items="items" class="mt-10" />
    <p v-else class="mt-10 text-meta text-ink-3">No entries for this day.</p>
</template>
