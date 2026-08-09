<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useClock } from '../../composables/useClock';
import { weatherFor } from '../../lib/weather.js';
import Tooltip from '../Ui/Tooltip.vue';
import ActivityRings from '../Stats/ActivityRings.vue';
import WeatherStatus from './WeatherStatus.vue';
import BatteryStatus from './BatteryStatus.vue';

const props = defineProps({
    temp: { type: String, default: '25°C' },
    condition: { type: String, default: 'partly-cloudy' },
    location: { type: String, default: 'Whyteleafe' },
    timezone: { type: String, default: 'BST' },
    move: { type: Number, default: 62 },
    exercise: { type: Number, default: 25 },
    stand: { type: Number, default: 75 },
    moveKcal: { type: [Number, String], default: 137 },
    exerciseMins: { type: [Number, String], default: 3 },
    standHrs: { type: [Number, String], default: 9 },
    batteryLevel: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: true },
    lowPower: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const { time, date } = useClock();
const page = usePage();

// Ambient readings shared from the server; each falls back to the prop (and so
// to this component's own default) until the phone has sent that value.
const battery = computed(() => page.props.ambient?.battery ?? {});
const weather = computed(() => page.props.ambient?.weather ?? {});
const location = computed(() => page.props.ambient?.location ?? {});
const rings = computed(() => page.props.ambient?.rings ?? {});

// A ring's completion, capped at 100 so an over-achieved goal doesn't overflow
// the arc. Falls back to the prop when the pair has not been sent.
function ringPercent(value, goal, fallback) {
    return value === undefined || !goal ? fallback : Math.min(100, Math.round((value / goal) * 100));
}

const temp = computed(() => (weather.value.temp === undefined ? props.temp : `${Math.round(weather.value.temp)}°C`));
const condition = computed(() => weather.value.condition ?? props.condition);
const place = computed(() => location.value.city ?? props.location);
const zone = computed(() => location.value.tzAbbr ?? props.timezone);
const move = computed(() => ringPercent(rings.value.move, rings.value.moveGoal, props.move));
const exercise = computed(() => ringPercent(rings.value.exercise, rings.value.exerciseGoal, props.exercise));
const stand = computed(() => ringPercent(rings.value.stand, rings.value.standGoal, props.stand));
const batteryLevel = computed(() => (battery.value.percent === undefined ? props.batteryLevel : battery.value.percent / 100));
const charging = computed(() => battery.value.charging ?? props.charging);
const lowPower = computed(() => battery.value.lowPower ?? props.lowPower);

/**
 * Two sources report today's steps: the count the phone pushes alongside the
 * rings, and the one the Rovi sync caches. The pushed count is preferred so the
 * tooltip agrees with the rings drawn beside it; the synced count covers the
 * days no Shortcut fires. Null when neither has reported, never a placeholder.
 */
const steps = computed(() => {
    const value = rings.value.steps ?? page.props.todaySteps;

    return value === undefined || value === null ? null : Number(value);
});

// Null while there is no count, which drops the tooltip rather than captioning
// the rings with a number we do not have.
const ringsLabel = computed(() => (steps.value === null ? null : `${steps.value.toLocaleString()} steps`));
// The condition arrives as a slug, so the label comes from the shared table
// rather than the raw value: this read "mostly-sunny in Whyteleafe".
const weatherLabel = computed(() => `${weatherFor(condition.value).label} in ${place.value}`);
// Mirrors the /now tile's wording, including its charging-beats-Low-Power
// order: a phone charging in Low Power Mode is still charging.
const batteryLabel = computed(() => {
    const percent = Math.round(batteryLevel.value * 100);

    if (charging.value) {
        return `${percent}%, charging`;
    }

    return `${percent}%, ${lowPower.value ? 'Low Power Mode' : 'on battery'}`;
});
</script>

<template>
    <Link
        href="/now"
        aria-label="Today's status - open the Now page"
        class="flex items-center font-medium text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900"
        :class="compact ? 'gap-2.5 text-sm' : 'gap-4 text-sm'"
    >
        <Tooltip v-if="ringsLabel" :label="ringsLabel">
            <ActivityRings :move="move" :exercise="exercise" :stand="stand" :compact="compact" />
        </Tooltip>
        <ActivityRings v-else :move="move" :exercise="exercise" :stand="stand" :compact="compact" />

        <Tooltip :label="weatherLabel">
            <WeatherStatus :temp="temp" :condition="condition" :compact="compact" />
        </Tooltip>

        <Tooltip :label="`${date}, ${zone}`">
            <span class="tnum">{{ time }}</span>
        </Tooltip>

        <Tooltip :label="batteryLabel">
            <BatteryStatus class="text-neutral-500" :level="batteryLevel" :charging="charging" :low-power="lowPower" :compact="compact" />
        </Tooltip>
    </Link>
</template>
