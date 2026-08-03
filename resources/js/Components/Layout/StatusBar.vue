<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { useClock } from '../../composables/useClock';
import Tooltip from '../Ui/Tooltip.vue';
import ActivityRings from '../Stats/ActivityRings.vue';
import WeatherStatus from '../Now/WeatherStatus.vue';
import BatteryStatus from '../Now/BatteryStatus.vue';

const props = defineProps({
    temp: { type: String, default: '25°C' },
    condition: { type: String, default: 'Partly Cloudy' },
    location: { type: String, default: 'Whyteleafe' },
    timezone: { type: String, default: 'BST' },
    move: { type: Number, default: 62 },
    exercise: { type: Number, default: 25 },
    stand: { type: Number, default: 75 },
    steps: { type: [Number, String], default: '11,240' },
    moveKcal: { type: [Number, String], default: 137 },
    exerciseMins: { type: [Number, String], default: 3 },
    standHrs: { type: [Number, String], default: 9 },
    batteryLevel: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const { time, date } = useClock();

// Ambient readings shared from the server; each falls back to the prop (and so
// to this component's own default) until the phone has sent that value.
const page = usePage();
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
// Steps stay on the placeholder until the Vitals table (#67) is fed from
// Rovi's daily step records; the phone deliberately does not send them.
const steps = computed(() => rings.value.steps ?? props.steps);
const move = computed(() => ringPercent(rings.value.move, rings.value.moveGoal, props.move));
const exercise = computed(() => ringPercent(rings.value.exercise, rings.value.exerciseGoal, props.exercise));
const stand = computed(() => ringPercent(rings.value.stand, rings.value.standGoal, props.stand));
const batteryLevel = computed(() => (battery.value.percent === undefined ? props.batteryLevel : battery.value.percent / 100));
const charging = computed(() => battery.value.charging ?? props.charging);

const ringsLabel = computed(() => `${steps.value} steps`);
const weatherLabel = computed(() => `${condition.value} in ${place.value}`);
const batteryLabel = computed(() => `${Math.round(batteryLevel.value * 100)}%, ${charging.value ? 'charging' : 'on battery'}`);
</script>

<template>
    <Link
        href="/now"
        aria-label="Today's status - open the Now page"
        class="flex items-center font-medium text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900"
        :class="compact ? 'gap-3 text-xs' : 'gap-4 text-sm'"
    >
        <Tooltip :label="ringsLabel">
            <ActivityRings :move="move" :exercise="exercise" :stand="stand" :compact="compact" />
        </Tooltip>

        <Tooltip :label="weatherLabel">
            <WeatherStatus :temp="temp" :condition="condition" :compact="compact" />
        </Tooltip>

        <Tooltip :label="`${date}, ${zone}`">
            <span class="tnum">{{ time }}</span>
        </Tooltip>

        <Tooltip :label="batteryLabel">
            <BatteryStatus class="text-neutral-500" :level="batteryLevel" :charging="charging" :compact="compact" />
        </Tooltip>
    </Link>
</template>
