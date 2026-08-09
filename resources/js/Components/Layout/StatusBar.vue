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
    moveKcal: { type: [Number, String], default: 137 },
    exerciseMins: { type: [Number, String], default: 3 },
    standHrs: { type: [Number, String], default: 9 },
    batteryLevel: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: true },
    compact: { type: Boolean, default: false },
});

const { time, date } = useClock();
const page = usePage();

// Today's steps are shared from the server rather than passed in, since this bar
// sits in the topbar on every page. Null until the day's first sync has run.
const steps = computed(() => page.props.todaySteps ?? null);

// Null while there is no count, which drops the tooltip rather than captioning
// the rings with a number we do not have.
const ringsLabel = computed(() => (steps.value === null ? null : `${steps.value.toLocaleString()} steps`));

// Computed, not plain consts: these read props that change on an Inertia visit,
// and a const would freeze the label at whatever it was on first render.
const weatherLabel = computed(() => `${props.condition} in ${props.location}`);
const batteryLabel = computed(() => `${Math.round(props.batteryLevel * 100)}%, ${props.charging ? 'charging' : 'on battery'}`);
</script>

<template>
    <Link
        href="/now"
        aria-label="Today's status - open the Now page"
        class="flex items-center font-medium text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900"
        :class="compact ? 'gap-3 text-xs' : 'gap-4 text-sm'"
    >
        <Tooltip v-if="ringsLabel" :label="ringsLabel">
            <ActivityRings :move="move" :exercise="exercise" :stand="stand" :compact="compact" />
        </Tooltip>
        <ActivityRings v-else :move="move" :exercise="exercise" :stand="stand" :compact="compact" />

        <Tooltip :label="weatherLabel">
            <WeatherStatus :temp="temp" :condition="condition" :compact="compact" />
        </Tooltip>

        <Tooltip :label="`${date}, ${timezone}`">
            <span class="tnum">{{ time }}</span>
        </Tooltip>

        <Tooltip :label="batteryLabel">
            <BatteryStatus class="text-neutral-500" :level="batteryLevel" :charging="charging" :compact="compact" />
        </Tooltip>
    </Link>
</template>
