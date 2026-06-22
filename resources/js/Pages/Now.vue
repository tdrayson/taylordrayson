<script setup>
import { Head, Link, setLayoutProps } from '@inertiajs/vue3';
import { Clock01Icon, SunCloud01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Icon.vue';
import NowRow from '../Components/NowRow.vue';
import ActivityRings from '../Components/ActivityRings.vue';
import BatteryStatus from '../Components/BatteryStatus.vue';
import { useClock } from '../composables/useClock';

defineOptions({ layout: AppLayout, inheritAttrs: false });

setLayoutProps({
    breadcrumb: [{ label: 'Now' }],
});

// Live clock until the timezone + time come from the data API.
const { time } = useClock();

// Hardcoded placeholder data — real data wiring deferred.
const now = {
    timezone: 'BST',
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
</script>

<template>
    <Head title="Now" />

    <h1 class="font-display text-display">Now</h1>

    <div class="mt-10 flex flex-col gap-6">
        <NowRow>
            <template #icon><Icon :icon="Clock01Icon" class="size-4 text-ink-3" /></template>
            The time is <strong>{{ time }}</strong> in my current timezone, {{ now.timezone }}.
        </NowRow>

        <NowRow>
            <template #icon><Icon :icon="SunCloud01Icon" class="size-4 text-ink-3" /></template>
            Right now I'm in {{ now.location }} where it's <strong>{{ now.temp }}</strong> and {{ now.condition }},
            with <strong>{{ now.humidity }}%</strong> humidity and a <strong>{{ now.windMph }} mph</strong> breeze.
        </NowRow>

        <NowRow>
            <template #icon><BatteryStatus :level="now.batteryPercent / 100" :charging="now.charging" /></template>
            My phone's battery level is <strong>{{ now.batteryPercent }}%</strong>{{ now.charging ? ' and currently charging.' : '.' }}
        </NowRow>

        <NowRow>
            <template #icon><ActivityRings /></template>
            I've taken <strong>{{ now.steps }} steps</strong>, burned <strong>{{ now.moveKcal }} kcal</strong>,
            exercised for <strong>{{ now.exerciseMins }} mins</strong> and stood up for <strong>{{ now.standHrs }} hrs</strong>.
        </NowRow>
    </div>

    <Link href="/working-on" class="mt-8 inline-flex items-center gap-2 text-lg font-medium text-accent transition-colors hover:text-accent-active">
        What I'm working on
        <Icon :icon="ArrowRight01Icon" class="size-5" />
    </Link>

    <p class="mt-10 border-t border-line-2 pt-5 text-caption text-ink-3">
        Last updated: {{ now.updatedAt }}
    </p>
</template>

<style scoped>
strong {
    font-weight: 600;
    color: var(--color-ink);
}
</style>
