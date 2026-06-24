<script setup>
import { Link } from '@inertiajs/vue3';
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

const ringsLabel = `${props.steps} steps`;
const weatherLabel = `${props.condition} in ${props.location}`;
const batteryLabel = `${Math.round(props.batteryLevel * 100)}% · ${props.charging ? 'charging' : 'on battery'}`;
</script>

<template>
    <Link
        href="/now"
        aria-label="Today's status — open the Now page"
        class="flex items-center font-medium text-ink-2 transition-colors hover:text-ink"
        :class="compact ? 'gap-3 text-xs' : 'gap-4 text-sm'"
    >
        <Tooltip :label="ringsLabel">
            <ActivityRings :move="move" :exercise="exercise" :stand="stand" :compact="compact" />
        </Tooltip>

        <Tooltip :label="weatherLabel">
            <WeatherStatus :temp="temp" :condition="condition" :compact="compact" />
        </Tooltip>

        <Tooltip :label="`${date}, ${timezone}`">
            <span class="tnum">{{ time }}</span>
        </Tooltip>

        <Tooltip :label="batteryLabel">
            <BatteryStatus class="text-ink-3" :level="batteryLevel" :charging="charging" :compact="compact" />
        </Tooltip>
    </Link>
</template>
