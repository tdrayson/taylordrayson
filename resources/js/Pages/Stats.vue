<script setup>
import { ref, computed, watch } from 'vue';
import { setLayoutProps, router } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import DateRangePicker from '../Components/Stats/DateRangePicker.vue';
import ComparisonSelect from '../Components/Stats/ComparisonSelect.vue';
import MetricCard from '../Components/Stats/MetricCard.vue';
import RouteHeatmap from '../Components/Stats/RouteHeatmap.vue';
import TypeBreakdown from '../Components/Stats/TypeBreakdown.vue';
import MiniBars from '../Components/Stats/MiniBars.vue';
import Chart from '../Components/Ui/Chart.vue';
import { PALETTE, baseOptions } from '../lib/chart.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    type: { type: String, required: true },
    og: { type: Object, default: () => ({}) },
    accent: { type: String, default: '#2ea06b' },
    range: { type: Object, default: () => ({ from: '', to: '', label: '' }) },
    compare: { type: Object, default: () => ({ mode: 'previous-period', label: 'previous period' }) },
    routes: { type: Array, default: () => [] },
    metrics: { type: Array, default: () => [] },
    averageLabel: { type: String, default: 'Weekly average' },
    perWeek: { type: Array, default: () => [] },
    byType: { type: Array, default: () => [] },
    busiest: { type: Object, default: () => ({ grid: [], max: 0 }) },
    trend: { type: Object, default: () => ({}) },
    records: { type: Array, default: () => [] },
});

const CARD = 'rounded-lg border border-neutral-50 bg-neutral-0 p-5';

const DAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
const HOUR_TICKS = { 0: '12am', 6: '6am', 12: '12pm', 18: '6pm', 23: '11pm' };

// 24h to a friendly am/pm label for the tooltip.
function hourLabel(hour) {
    if (hour === 0) return '12am';
    if (hour === 12) return '12pm';

    return hour < 12 ? `${hour}am` : `${hour - 12}pm`;
}

const count = (value) => `${value} ${value === 1 ? 'activity' : 'activities'}`;

// Collapse the day x hour grid onto each axis for the two mini bar charts.
const byHour = computed(() => Array.from({ length: 24 }, (_, hour) => {
    const value = props.busiest.grid.reduce((sum, row) => sum + (row[hour] || 0), 0);

    return { value, tick: HOUR_TICKS[hour] ?? '', label: `${hourLabel(hour)}, ${count(value)}` };
}));

// By day of week as ranked horizontal bars (Mon-Sun order).
const byDay = computed(() => props.busiest.grid.map((row, index) => ({
    label: DAYS[index],
    value: row.reduce((sum, cell) => sum + (cell || 0), 0),
})));

// Duration trend at a selectable granularity; the range dictates which buckets
// exist, so reset to the server's default whenever that set changes.
const trendBuckets = computed(() => props.trend.buckets ?? []);
const activeBucket = ref(props.trend.default ?? 'Month');
watch(() => props.trend.default, (value) => { activeBucket.value = value; });
const selectedBucket = computed(() => trendBuckets.value.find((bucket) => bucket.label === activeBucket.value)
    ?? trendBuckets.value.find((bucket) => bucket.label === props.trend.default)
    ?? trendBuckets.value[0]
    ?? { labels: [], values: [] });

// Selecting a range or comparison reloads the data props for the new window.
function visit(params) {
    router.get(window.location.pathname, { from: props.range.from, to: props.range.to, compare: props.compare.mode, ...params }, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function onRange({ from, to }) {
    visit({ from, to });
}

function onCompare(mode) {
    visit({ compare: mode });
}

const trendChart = computed(() => ({
    labels: selectedBucket.value.labels ?? [],
    datasets: [
        { data: selectedBucket.value.values ?? [], borderColor: props.accent, backgroundColor: `${props.accent}22`, fill: true, tension: 0.35, pointRadius: 0, borderWidth: 2 },
    ],
}));

const trendOptions = baseOptions({
    scales: { x: { ticks: { color: PALETTE.mid, maxTicksLimit: 12, autoSkip: true } } },
    plugins: { tooltip: { callbacks: { label: (ctx) => `${ctx.raw}${props.trend.unit ?? ''}` } } },
});

setLayoutProps({
    breadcrumb: [{ label: props.og?.eyebrow ?? 'Stats' }],
});
</script>

<template>
    <AppHead :og="og" />

    <div class="full-width-inset mx-auto flex w-full max-w-dashboard flex-col gap-5">
        <!-- Plain title (no eyebrow) with the range picker alongside; deltas below
             each metric are measured against the previous period. -->
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="font-display text-display">{{ og.heading }}</h1>
            <div class="flex flex-wrap items-center gap-3">
                <ComparisonSelect :mode="compare.mode" @change="onCompare" />
                <DateRangePicker :label="range.label" @change="onRange" />
            </div>
        </div>

        <!-- Metric cards: value, delta vs previous period, and the metric's own sparkline. -->
        <dl class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-6">
            <MetricCard
                v-for="metric in metrics"
                :key="metric.label"
                :value="metric.value"
                :label="metric.label"
                :unit="metric.unit"
                :delta="metric.delta"
                :spark="metric.spark"
                :accent="accent"
            />
        </dl>

        <div class="grid grid-cols-1 gap-5 lg:grid-cols-12">
            <!-- Route map: solid routes in the type colour. -->
            <section class="flex h-[440px] flex-col lg:col-span-8" :class="CARD">
                <header class="mb-4 flex items-baseline justify-between gap-3">
                    <h2 class="font-display text-item-title">Routes</h2>
                    <span class="text-meta text-neutral-500">{{ routes.length }} routes</span>
                </header>
                <div class="min-h-0 flex-1">
                    <RouteHeatmap :polylines="routes" :color="accent" />
                </div>
            </section>

            <!-- Right column: two stacked cards fill the map's height. -->
            <div class="flex flex-col gap-5 lg:col-span-4">
                <!-- An average week over the range: honest averages, no goals. -->
                <section :class="CARD">
                    <h2 class="mb-4 font-display text-item-title">{{ averageLabel }}</h2>
                    <dl class="flex flex-col gap-4">
                        <div v-for="item in perWeek" :key="item.label" class="flex items-baseline justify-between gap-3">
                            <dt class="text-meta text-neutral-500">{{ item.label }}</dt>
                            <dd class="font-display text-item-title tnum text-neutral-900">{{ item.display }}</dd>
                        </div>
                    </dl>
                </section>

                <!-- Records / PRs. -->
                <section class="flex flex-1 flex-col" :class="CARD">
                    <h2 class="mb-4 font-display text-item-title">Records</h2>
                    <dl class="flex flex-col gap-4">
                        <div v-for="record in records" :key="record.label" class="flex items-baseline justify-between gap-3">
                            <dt class="text-meta text-neutral-500">{{ record.label }}</dt>
                            <dd class="font-display text-item-title tnum text-neutral-900">{{ record.value }}</dd>
                        </div>
                    </dl>
                </section>
            </div>

            <!-- Duration trend, bucketed by the granularity that fits the range. -->
            <section class="lg:col-span-12" :class="CARD">
                <header class="mb-2 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-display text-item-title">{{ trend.metric }} per {{ activeBucket.toLowerCase() }}</h2>
                    <div class="inline-flex rounded-lg border border-neutral-50 p-1 text-sm">
                        <button
                            v-for="bucket in trendBuckets"
                            :key="bucket.label"
                            type="button"
                            class="rounded-md px-3 py-1 font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                            :class="bucket.label === activeBucket ? 'bg-neutral-25 text-neutral-900' : 'text-neutral-500 hover:text-neutral-900'"
                            @click="activeBucket = bucket.label"
                        >{{ bucket.label }}</button>
                    </div>
                </header>
                <Chart type="line" :data="trendChart" :options="trendOptions" :height="240" :summary="`${trend.metric} per ${activeBucket.toLowerCase()} across the range.`" />
            </section>

            <!-- Three even boxes: what, which day, what time. -->
            <section class="lg:col-span-4" :class="CARD">
                <h2 class="mb-4 font-display text-item-title">By type</h2>
                <TypeBreakdown :items="byType" :accent="accent" />
            </section>

            <section class="lg:col-span-4" :class="CARD">
                <h2 class="mb-4 font-display text-item-title">By day of week</h2>
                <TypeBreakdown :items="byDay" :accent="accent" />
            </section>

            <section class="lg:col-span-4" :class="CARD">
                <h2 class="mb-6 font-display text-item-title">By time of day</h2>
                <MiniBars :items="byHour" :accent="accent" />
            </section>
        </div>
    </div>
</template>
