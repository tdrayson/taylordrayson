<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    fill: { type: Boolean, default: false },
    // Last 7 nights, oldest first: [{ date: 'YYYY-MM-DD', hours: Number|null }].
    // Each carries its own date, so a missing night leaves a gap here rather
    // than shifting every label onto the wrong day.
    nights: { type: Array, default: () => [] },
    // The night the headline shows, or null when neither of the last two has
    // data: { date, hours, stageHours: { deep, core, rem, awake } }.
    lastNight: { type: Object, default: null },
});

const MAX = 9;

// Map the raw stage hours to their display label and design-token swatch.
const stages = computed(() => {
    const split = props.lastNight?.stageHours;

    return split === undefined || split === null ? [] : [
        { key: 'Deep', hours: split.deep, swatch: 'bg-sleep-deep' },
        { key: 'Core', hours: split.core, swatch: 'bg-sleep' },
        { key: 'REM', hours: split.rem, swatch: 'bg-sleep-rem' },
        { key: 'Awake', hours: split.awake, swatch: 'bg-sleep-awake' },
    ];
});

function fmtParts(h) {
    const whole = Math.floor(h);
    const mins = Math.round((h - whole) * 60);
    return { hours: whole, minutes: String(mins).padStart(2, '0') };
}

/** A date-only string read as a local day, not as UTC midnight. */
function dayOf(date) {
    const [year, month, day] = date.split('-').map(Number);

    return new Date(year, month - 1, day);
}

const bigParts = computed(() => (props.lastNight ? fmtParts(props.lastNight.hours) : null));

// Nights with no record are left out of the average rather than counted as zero.
const recorded = computed(() => props.nights.filter((night) => night.hours !== null));
const averageParts = computed(() => (recorded.value.length === 0
    ? null
    : fmtParts(recorded.value.reduce((total, night) => total + night.hours, 0) / recorded.value.length)));

// Per-night bar: its own date, a link to that day and a tooltip. A night with
// no record still takes its column, so the row stays a calendar week.
const nightCells = computed(() => props.nights.map((night, i) => {
    const day = dayOf(night.date);
    const label = day.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
    const parts = night.hours === null ? null : fmtParts(night.hours);

    return {
        date: night.date,
        href: `/${night.date.replaceAll('-', '/')}`,
        label: parts === null ? `${label}, no sleep recorded` : `${label}, ${parts.hours}h ${parts.minutes}m`,
        heightPct: parts === null ? null : `${(night.hours / MAX) * 100}%`,
        today: i === props.nights.length - 1,
    };
}));

// Read off the same dates the bars use, so a letter can never disagree with
// the column above it.
const days = computed(() => props.nights.map((night, i) => ({
    letter: dayOf(night.date).toLocaleDateString('en-GB', { weekday: 'narrow' }),
    today: i === props.nights.length - 1,
})));
</script>

<template>
    <div class="@container rounded-3xl bg-neutral-0 text-neutral-900 shadow-card" :class="{ 'aspect-2/1': !fill }">
        <div class="flex h-full gap-4 px-4.5 py-4 @sm:gap-5 @sm:px-5.5 @sm:py-5 @md:gap-6 @md:px-6.5 @md:py-6 @xl:gap-8 @xl:px-9 @xl:py-8">
            <div class="flex min-w-0 shrink-0 basis-3/7 flex-col">
                <h2 class="flex items-center gap-1 text-2xs font-bold text-neutral-500 @sm:gap-1.5 @sm:text-xs @md:gap-2 @md:text-sm @xl:gap-2.5 @xl:text-xl">
                    <Icon class="size-3 shrink-0 text-sleep @sm:size-3.5 @md:size-4.5 @xl:size-6" name="Moon02Icon" />Sleep
                </h2>
                <div v-if="bigParts" class="mt-1 text-3xl leading-none font-extrabold tracking-tight @sm:mt-1.5 @sm:text-4xl @md:text-5xl @xl:mt-2 @xl:text-6xl">
                    {{ bigParts.hours }}h <small class="ml-0.5 text-sm leading-none font-bold text-neutral-400 @sm:text-lg @md:text-xl @xl:text-3xl">{{ bigParts.minutes }}m</small>
                </div>
                <div v-else class="mt-1 text-3xl leading-none font-extrabold tracking-tight text-neutral-400 @sm:mt-1.5 @sm:text-4xl @md:text-5xl @xl:mt-2 @xl:text-6xl">N/A</div>
                <div class="mt-0.5 text-2xs font-medium text-neutral-500 @sm:mt-0.75 @sm:text-xs @md:mt-1 @md:text-sm @xl:mt-1.25 @xl:text-lg">Last night</div>

                <!-- No split to draw without a night to draw it from. -->
                <template v-if="stages.length">
                    <div class="mt-auto flex h-2 overflow-hidden rounded-full @sm:h-2.5 @md:h-3 @xl:h-4">
                        <i v-for="stage in stages" :key="stage.key" class="h-full" :class="stage.swatch" :style="{ flex: stage.hours }" />
                    </div>
                    <div class="mt-1.5 flex gap-1.5 @sm:mt-2 @sm:gap-2.5 @md:mt-2.5 @md:gap-3 @xl:mt-3 @xl:gap-4">
                        <span
                            v-for="stage in stages"
                            :key="stage.key"
                            class="flex items-center gap-0.75 text-3xs font-semibold text-neutral-500 @sm:gap-1 @sm:text-2xs @md:text-xs @xl:gap-1.5 @xl:text-sm"
                        >
                            <b class="size-1.25 shrink-0 rounded-full @sm:size-1.5 @md:size-2 @xl:size-2.5" :class="stage.swatch" />{{ stage.key }}
                        </span>
                    </div>
                </template>
            </div>

            <div class="flex min-w-0 flex-1 flex-col">
                <div class="flex items-baseline justify-between text-3xs @sm:text-2xs @md:text-xs @xl:text-base">
                    <h3 class="font-bold tracking-wider text-neutral-500">LAST 7 NIGHTS</h3>
                    <span v-if="averageParts" class="font-semibold text-neutral-400">avg {{ averageParts.hours }}h {{ averageParts.minutes }}m</span>
                </div>
                <div class="mt-1.25 mb-0.75 flex flex-1 gap-1.5 @sm:mt-1.5 @sm:mb-1 @sm:gap-2 @md:mt-2 @md:mb-1.25 @md:gap-2.5 @xl:mt-2.5 @xl:mb-1.5 @xl:gap-3.5">
                    <Tooltip v-for="night in nightCells" :key="night.date" :label="night.label" placement="top" class="min-w-0 flex-1 flex-col justify-end">
                        <!-- A night with no record keeps its column but draws no
                             bar, so the row still reads as a calendar week. -->
                        <Link
                            v-if="night.heightPct"
                            :href="night.href"
                            class="min-h-1/25 w-full rounded-t rounded-b-xs transition duration-120 hover:brightness-92 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500 @xl:rounded-t-sm"
                            :class="night.today ? 'bg-sleep' : 'bg-sleep/25'"
                            :style="{ height: night.heightPct }"
                            :aria-label="night.label"
                        />
                        <span v-else :aria-label="night.label" />
                    </Tooltip>
                </div>
                <div class="flex text-3xs font-semibold text-neutral-400 @sm:text-2xs @md:text-xs @xl:text-base">
                    <span v-for="(day, i) in days" :key="i" class="flex-1 text-center" :class="{ 'text-sleep': day.today }">{{ day.letter }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
