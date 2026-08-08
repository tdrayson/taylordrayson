<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    fill: { type: Boolean, default: false },
    // Last 7 nights in hours, most recent last.
    nights: { type: Array, default: () => [6.8, 7.4, 6.2, 8.1, 7.0, 5.9, 7.53] },
    // Last night's stage split in hours. Colours/labels stay presentational here.
    stageHours: { type: Object, default: () => ({ deep: 1.2, core: 3.9, rem: 1.6, awake: 0.4 }) },
});

const MAX = 9;

// Map the raw stage hours to their display label and design-token colour.
const stages = computed(() => [
    { key: 'Deep', hours: props.stageHours.deep, color: 'var(--color-sleep-deep)' },
    { key: 'Core', hours: props.stageHours.core, color: 'var(--color-sleep)' },
    { key: 'REM', hours: props.stageHours.rem, color: 'var(--color-sleep-rem)' },
    { key: 'Awake', hours: props.stageHours.awake, color: 'var(--color-sleep-awake)' },
]);

function fmtParts(h) {
    const whole = Math.floor(h);
    const mins = Math.round((h - whole) * 60);
    return { hours: whole, minutes: String(mins).padStart(2, '0') };
}

const last = computed(() => props.nights[props.nights.length - 1]);
const bigParts = computed(() => fmtParts(last.value));
const average = computed(() => props.nights.reduce((a, b) => a + b, 0) / props.nights.length);
const averageParts = computed(() => fmtParts(average.value));

// Per-night bar: date, link to that day and tooltip.
const nightCells = computed(() => {
    const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/London' }));
    today.setHours(0, 0, 0, 0);

    return props.nights.map((h, i) => {
        const d = new Date(today);
        d.setDate(d.getDate() - (props.nights.length - 1 - i));
        const year = d.getFullYear();
        const month = String(d.getMonth() + 1).padStart(2, '0');
        const day = String(d.getDate()).padStart(2, '0');
        const date = d.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'short' });
        const parts = fmtParts(h);

        return {
            href: `/${year}/${month}/${day}`,
            label: `${date}, ${parts.hours}h ${parts.minutes}m`,
            heightPct: `${(h / MAX) * 100}%`,
            today: i === props.nights.length - 1,
        };
    });
});

// Weekday letters ending today (London).
const days = computed(() => {
    const letters = ['S', 'M', 'T', 'W', 'T', 'F', 'S'];
    const today = new Date(new Date().toLocaleString('en-US', { timeZone: 'Europe/London' })).getDay();
    const out = [];
    for (let i = 6; i >= 0; i--) {
        out.push({ letter: letters[(today - i + 7) % 7], today: i === 0 });
    }
    return out;
});
</script>

<template>
    <div class="sleep rounded-3xl" :class="{ 'sleep--has-aspect': !fill }">
        <div class="sleep__inner">
            <div class="sleep__summary">
                <h2 class="sleep__label"><Icon class="sleep__label-icon" name="Moon02Icon" />Sleep</h2>
                <div class="sleep__duration">{{ bigParts.hours }}h <small class="sleep__minutes">{{ bigParts.minutes }}m</small></div>
                <div class="sleep__caption">Last night</div>
                <div class="sleep__stages">
                    <i v-for="stage in stages" :key="stage.key" class="sleep__stage-segment" :style="{ flex: stage.hours, background: stage.color }" />
                </div>
                <div class="sleep__legend">
                    <span v-for="stage in stages" :key="stage.key" class="sleep__legend-item">
                        <b class="sleep__legend-swatch" :style="{ background: stage.color }" />{{ stage.key }}
                    </span>
                </div>
            </div>

            <div class="sleep__chart">
                <div class="sleep__chart-header">
                    <h3 class="sleep__chart-title">LAST 7 NIGHTS</h3>
                    <span class="sleep__chart-average">avg {{ averageParts.hours }}h {{ averageParts.minutes }}m</span>
                </div>
                <div class="sleep__bars">
                    <Tooltip v-for="night in nightCells" :key="night.href" :label="night.label" placement="top" class="sleep__bar-column">
                        <Link
                            :href="night.href"
                            class="sleep__bar"
                            :class="{ 'sleep__bar--today': night.today }"
                            :style="{ height: night.heightPct }"
                            :aria-label="night.label"
                        />
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
