<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import {
    WorkoutRunIcon,
    Airplane01Icon,
    Film01Icon,
    Moon02Icon,
    Rocket01Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import Heatmap from '../Components/Stats/Heatmap.vue';
import BarList from '../Components/Stats/BarList.vue';
import FutureNote from '../Components/Timeline/FutureNote.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    og: { type: Object, default: () => ({}) },
});

const isFuture = computed(() => props.year > new Date().getFullYear());

setLayoutProps({
    breadcrumb: [{ label: String(props.year) }],
});

// Hardcoded placeholder data — real data wiring deferred.
const numbers = [
    { value: '212', label: 'Runs' },
    { value: '1,840', unit: 'km', label: 'Distance run' },
    { value: '23', label: 'Flights' },
    { value: '143', label: 'Films' },
    { value: '11', label: 'Books' },
    { value: '1,310', label: 'Coffees' },
];

const distance = [
    { label: 'W16', width: 71, value: '38.2 km' },
    { label: 'W17', width: 45, value: '24.5 km' },
    { label: 'W18', width: 76, value: '41.0 km' },
    { label: 'W19', width: 55, value: '29.8 km' },
    { label: 'W20', width: 84, value: '45.6 km' },
    { label: 'W21', width: 61, value: '33.1 km' },
    { label: 'W22', width: 89, value: '48.2 km' },
    { label: 'W23', width: 67, value: '36.4 km' },
];

const activityMix = [
    { label: 'Run', width: 58, value: '58%' },
    { label: 'Walk', width: 16, value: '16%', accent: true },
    { label: 'Padel', width: 11, value: '11%' },
    { label: 'Tennis', width: 9, value: '9%' },
    { label: 'Other', width: 6, value: '6%' },
];

const places = [
    { rank: '01', name: 'David Lloyd', sub: 'tennis · Croydon', value: '86' },
    { rank: '02', name: 'Aerodrome Hotel', sub: 'BNI · Croydon', value: '44' },
    { rank: '03', name: 'Cineworld', sub: 'cinema · Croydon', value: '38' },
    { rank: '04', name: 'Example Coffee Co.', sub: 'coffee · London', value: '31' },
    { rank: '05', name: 'South Norwood Park', sub: 'park · London', value: '22' },
];

const highlights = [
    { icon: WorkoutRunIcon, label: 'Longest run', value: '21.1 km', sub: 'Croydon Half · 12 Apr' },
    { icon: Airplane01Icon, label: 'Furthest flight', value: 'London → Singapore', sub: '6,760 mi · 3 Mar' },
    { icon: Film01Icon, label: 'Top rated film', value: 'The Brutalist', sub: '9 / 10 · 16 Mar' },
    { icon: Moon02Icon, label: 'Best sleep month', value: 'February', sub: 'avg 7h 38m' },
    { icon: Rocket01Icon, label: 'Shipped', value: '4 projects', sub: 'Guestlist, data hub +2' },
];
</script>

<template>
    <AppHead :og="og" />

    <FutureNote v-if="isFuture" unit="year" />

    <template v-else>
    <!-- Not a data-type page: plain H1, no eyebrow. -->
    <ViewHeader
        :title="String(year)"
        subtitle="76 days in · 186 activities, 31 places, 8 flights, 9 films and 2,184 meals logged."
        :prev="{ label: String(year - 1), href: `/${year - 1}` }"
        :next="{ label: String(year + 1), href: `/${year + 1}` }"
    />

    <StatGrid :stats="numbers" size="lg" class="mt-8" />

    <SectionHead title="Every day this year" meta="2,118 entries" />
    <Heatmap />

    <SectionHead title="Movement" meta="212 activities · 1,840 km" />
    <div class="grid gap-10 sm:grid-cols-2">
        <div>
            <div class="mb-3 text-caption font-semibold text-neutral-700">Distance by week</div>
            <BarList :bars="distance" />
        </div>
        <div>
            <div class="mb-3 text-caption font-semibold text-neutral-700">Activity mix</div>
            <BarList :bars="activityMix" />
        </div>
    </div>

    <SectionHead title="Places" meta="312 check-ins · 31 unique" />
    <div class="flex flex-col">
        <div v-for="place in places" :key="place.rank" class="flex items-center gap-3 border-b border-neutral-50 py-3 last:border-0">
            <span class="w-6 flex-none text-xs text-neutral-500 tnum">{{ place.rank }}</span>
            <div class="flex-1">
                <div class="text-meta font-semibold">{{ place.name }}</div>
                <div class="text-xs text-neutral-500">{{ place.sub }}</div>
            </div>
            <span class="font-display text-base font-bold tnum">{{ place.value }}</span>
        </div>
    </div>

    <SectionHead title="Highlights" meta="of the year so far" />
    <div class="flex flex-col">
        <div v-for="item in highlights" :key="item.label" class="flex items-baseline justify-between gap-4 border-b border-neutral-50 py-3 last:border-0">
            <span class="flex items-center gap-3 text-meta text-neutral-500">
                <Icon :icon="item.icon" class="size-4 flex-none" />
                {{ item.label }}
            </span>
            <span class="text-right font-display text-base font-bold">
                {{ item.value }}
                <span class="block font-sans text-xs font-normal text-neutral-500">{{ item.sub }}</span>
            </span>
        </div>
    </div>
    </template>
</template>
