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

// Map the raw stage hours to their display label and design-token colour.
const stages = computed(() => {
    const split = props.lastNight?.stageHours;

    return split === undefined || split === null ? [] : [
        { key: 'Deep', hours: split.deep, color: 'var(--color-sleep-deep)' },
        { key: 'Core', hours: split.core, color: 'var(--color-sleep)' },
        { key: 'REM', hours: split.rem, color: 'var(--color-sleep-rem)' },
        { key: 'Awake', hours: split.awake, color: 'var(--color-sleep-awake)' },
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
    <div class="sleep rounded-3xl" :class="{ 'sleep--has-aspect': !fill }">
        <div class="sleep__inner">
            <div class="sleep__summary">
                <h2 class="sleep__label"><Icon class="sleep__label-icon" name="Moon02Icon" />Sleep</h2>
                <div v-if="bigParts" class="sleep__duration">{{ bigParts.hours }}h <small class="sleep__minutes">{{ bigParts.minutes }}m</small></div>
                <div v-else class="sleep__duration sleep__duration--empty">N/A</div>
                <div class="sleep__caption">Last night</div>

                <!-- No split to draw without a night to draw it from. -->
                <template v-if="stages.length">
                    <div class="sleep__stages">
                        <i v-for="stage in stages" :key="stage.key" class="sleep__stage-segment" :style="{ flex: stage.hours, background: stage.color }" />
                    </div>
                    <div class="sleep__legend">
                        <span v-for="stage in stages" :key="stage.key" class="sleep__legend-item">
                            <b class="sleep__legend-swatch" :style="{ background: stage.color }" />{{ stage.key }}
                        </span>
                    </div>
                </template>
            </div>

            <div class="sleep__chart">
                <div class="sleep__chart-header">
                    <h3 class="sleep__chart-title">LAST 7 NIGHTS</h3>
                    <span v-if="averageParts" class="sleep__chart-average">avg {{ averageParts.hours }}h {{ averageParts.minutes }}m</span>
                </div>
                <div class="sleep__bars">
                    <Tooltip v-for="night in nightCells" :key="night.date" :label="night.label" placement="top" class="sleep__bar-column">
                        <!-- A night with no record keeps its column but draws no
                             bar, so the row still reads as a calendar week. -->
                        <Link
                            v-if="night.heightPct"
                            :href="night.href"
                            class="sleep__bar"
                            :class="{ 'sleep__bar--today': night.today }"
                            :style="{ height: night.heightPct }"
                            :aria-label="night.label"
                        />
                        <span v-else class="sleep__bar sleep__bar--empty" :aria-label="night.label" />
                    </Tooltip>
                </div>
                <div class="sleep__days">
                    <span v-for="(day, i) in days" :key="i" class="sleep__day" :class="{ 'sleep__day--today': day.today }">{{ day.letter }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 4.52). Padding/flex live on .sleep__inner so cqw references the card. */
.sleep {
    container-type: inline-size;
    color: var(--color-neutral-900);
    background: var(--color-neutral-0);
    box-shadow: var(--shadow-card);
}

.sleep--has-aspect {
    aspect-ratio: 2 / 1;
}

.sleep__inner {
    display: flex;
    height: 100%;
    gap: 4.9cqw;
    padding: 4.9cqw 5.3cqw;
}

.sleep__summary {
    flex: 0 0 43%;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sleep__label {
    display: flex;
    align-items: center;
    gap: 1.5cqw;
    font-size: 2.9cqw;
    font-weight: 700;
    color: var(--color-neutral-500);
}

.sleep__label-icon {
    width: 3.5cqw;
    height: 3.5cqw;
    flex: none;
    color: var(--color-sleep);
}

.sleep__duration {
    margin-top: 1.3cqw;
    font-size: 8.4cqw;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1;
}

.sleep__minutes {
    margin-left: 0.4cqw;
    font-size: 4.2cqw;
    font-weight: 700;
    color: var(--color-neutral-400);
}

.sleep__duration--empty {
    color: var(--color-neutral-400);
}

.sleep__caption {
    margin-top: 0.7cqw;
    font-size: 2.8cqw;
    font-weight: 500;
    color: var(--color-neutral-500);
}

.sleep__stages {
    display: flex;
    height: 2.4cqw;
    margin-top: auto;
    border-radius: 1.3cqw;
    overflow: hidden;
}

.sleep__stage-segment {
    height: 100%;
}

.sleep__legend {
    display: flex;
    gap: 2.4cqw;
    margin-top: 2cqw;
}

.sleep__legend-item {
    display: flex;
    align-items: center;
    gap: 0.9cqw;
    font-size: 2.2cqw;
    font-weight: 600;
    color: var(--color-neutral-500);
}

.sleep__legend-swatch {
    width: 1.5cqw;
    height: 1.5cqw;
    flex: none;
    border-radius: 50%;
}

.sleep__chart {
    flex: 1;
    display: flex;
    flex-direction: column;
    min-width: 0;
}

.sleep__chart-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
}

.sleep__chart-title {
    font-size: 2.4cqw;
    font-weight: 700;
    letter-spacing: 0.04em;
    color: var(--color-neutral-500);
}

.sleep__chart-average {
    font-size: 2.4cqw;
    font-weight: 600;
    color: var(--color-neutral-400);
}

.sleep__bars {
    flex: 1;
    display: flex;
    align-items: stretch;
    gap: 2cqw;
    margin: 1.6cqw 0 1cqw;
}

.sleep__bar-column {
    display: flex;
    flex: 1;
    min-width: 0;
    flex-direction: column;
    justify-content: flex-end;
}

.sleep__bar {
    width: 100%;
    min-height: 4%;
    border-radius: 1.2cqw 1.2cqw 0.4cqw 0.4cqw;
    background: color-mix(in srgb, var(--color-sleep) 25%, var(--color-neutral-0));
    transition: filter 0.12s ease;
}

/* A night with no record: the column is still there to keep the week aligned,
   but there is nothing to say about its height. */
.sleep__bar--empty {
    height: 0;
    min-height: 0;
    background: none;
}

.sleep__bar--today {
    background: var(--color-sleep);
}

.sleep__bar:hover {
    filter: brightness(0.92);
}

.sleep__bar:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: 2px;
}

.sleep__days {
    display: flex;
}

.sleep__day {
    flex: 1;
    text-align: center;
    font-size: 2.4cqw;
    font-weight: 600;
    color: var(--color-neutral-400);
}

.sleep__day--today {
    color: var(--color-sleep);
}
</style>
