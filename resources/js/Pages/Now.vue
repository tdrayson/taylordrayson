<script setup>
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Icon.vue';
import ActivityRings from '../Components/ActivityRings.vue';
import AnalogueClock from '../Components/AnalogueClock.vue';
import BigBattery from '../Components/BigBattery.vue';
import WeatherScene from '../Components/WeatherScene.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

setLayoutProps({
    breadcrumb: [{ label: 'Now' }],
});

// Hardcoded placeholder data — real data wiring deferred.
const now = {
    timezone: 'British Summer Time',
    location: 'Whyteleafe',
    temp: '25°C',
    condition: 'partly cloudy',
    humidity: 68,
    windMph: '9.7',
    batteryPercent: 69,
    charging: true,
    steps: '11,240',
    moveKcal: 137,
    exerciseMins: 3,
    standHrs: 9,
    updatedAt: 'Mon 9 October 2023, 9:00am',
};

const activityStats = [
    { label: 'Steps', value: now.steps },
    { label: 'Move', value: `${now.moveKcal} kcal` },
    { label: 'Exercise', value: `${now.exerciseMins} min` },
    { label: 'Stand', value: `${now.standHrs} hrs` },
];
</script>

<template>
    <Head title="Now" />

    <div class="breakout">
        <header>
            <h1 class="font-display text-display">Now</h1>
            <p class="mt-2 text-meta text-ink-3">A live snapshot of my world, ticking away right this second.</p>
        </header>

        <div class="bento mt-10 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <!-- Activity rings — the big feature box -->
            <div class="tile tile-activity sm:col-span-2 lg:col-span-2 lg:row-span-2">
                <span class="text-eyebrow uppercase opacity-70">Today's rings</span>
                <div class="mt-auto flex flex-wrap items-end gap-x-8 gap-y-6">
                    <ActivityRings huge animate class="rings-pop shrink-0" />
                    <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-4">
                        <div v-for="stat in activityStats" :key="stat.label">
                            <dt class="text-label uppercase opacity-60">{{ stat.label }}</dt>
                            <dd class="font-display text-stat leading-none tnum">{{ stat.value }}</dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Analogue clock — small square, pops above the box -->
            <div class="tile tile-time items-center text-center sm:col-span-1 lg:col-span-1">
                <span class="self-start text-eyebrow uppercase opacity-70">Time</span>
                <div class="clock-pop">
                    <AnalogueClock />
                </div>
                <p class="mt-auto text-label uppercase opacity-80">{{ now.timezone }}</p>
            </div>

            <!-- Weather — small square, scene pops out -->
            <div class="tile tile-weather sm:col-span-1 lg:col-span-1">
                <span class="text-eyebrow uppercase opacity-70">Weather</span>
                <WeatherScene class="weather-pop" :size="104" />
                <div class="mt-auto">
                    <div class="font-display text-stat-lg leading-none">{{ now.temp }}</div>
                    <p class="mt-1 text-meta capitalize opacity-80">{{ now.condition }}</p>
                </div>
            </div>

            <!-- Battery — wide box, charging animation -->
            <div class="tile tile-battery sm:col-span-2 lg:col-span-2">
                <div class="flex items-center justify-between">
                    <span class="text-eyebrow uppercase opacity-70">Phone battery</span>
                    <span class="font-display text-stat leading-none tnum">{{ now.batteryPercent }}%</span>
                </div>
                <div class="mt-auto flex items-end justify-between gap-4">
                    <BigBattery class="battery-pop" :level="now.batteryPercent / 100" :charging="now.charging" />
                    <p class="shrink-0 text-meta opacity-80">{{ now.charging ? 'Charging' : 'On battery' }}</p>
                </div>
            </div>

            <!-- Working on — full-width banner CTA -->
            <Link href="/working-on" class="tile tile-cta group sm:col-span-2 lg:col-span-4">
                <span class="text-eyebrow uppercase opacity-80">Up next</span>
                <div class="mt-auto flex items-end justify-between gap-3">
                    <span class="font-display text-stat leading-none">What I'm working on</span>
                    <Icon :icon="ArrowRight01Icon" class="size-7 shrink-0 transition-transform group-hover:translate-x-1" />
                </div>
            </Link>
        </div>

        <p class="mt-6 text-caption text-ink-3">Last updated: {{ now.updatedAt }}</p>
    </div>
</template>

<style scoped>
@media (min-width: 1024px) {
    .bento {
        grid-auto-rows: 11rem;
    }
}

.tile {
    position: relative;
    display: flex;
    flex-direction: column;
    min-height: 11rem;
    padding: 1.5rem;
    border-radius: var(--radius-lg);
    /* Let the big graphics spill past the edges for a playful, tactile feel. */
    overflow: visible;
}

.tile-activity {
    background: #fde7ee;
    color: #b3123f;
}

.tile-time {
    background: var(--color-accent-tint);
    color: var(--color-accent-active);
}

.tile-weather {
    background: #fdeede;
    color: #9a4f12;
}

.tile-battery {
    background: #e7f6ec;
    color: #1c7a3c;
}

.tile-cta {
    background: var(--color-accent);
    color: var(--color-canvas);
    transition: background-color 0.15s ease;
}

.tile-cta:hover {
    background: var(--color-accent-active);
}

/* Pop-outs — nudge, tilt and shadow key elements so they break the box. */
.rings-pop {
    transform: rotate(-6deg);
    filter: drop-shadow(0 12px 16px rgba(0, 0, 0, 0.13));
}

.clock-pop {
    width: 8.5rem;
    height: 8.5rem;
    margin: -2.25rem 0 auto;
    transform: rotate(-5deg);
    filter: drop-shadow(0 12px 18px rgba(0, 0, 0, 0.16));
}

.weather-pop {
    position: absolute;
    top: -2rem;
    right: -1rem;
    transform: rotate(6deg);
    pointer-events: none;
}

.battery-pop {
    transform: rotate(-4deg);
    filter: drop-shadow(0 12px 16px rgba(0, 0, 0, 0.14));
}

@media (prefers-reduced-motion: reduce) {
    .rings-pop,
    .clock-pop,
    .battery-pop {
        transform: none;
    }
}
</style>
