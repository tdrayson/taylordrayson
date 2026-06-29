<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({ og: { type: Object, default: () => ({}) } });

setLayoutProps({ breadcrumb: [{ label: 'Sleep score' }] });

const components = [
    {
        name: 'Duration',
        max: 50,
        body: 'Half the score is simply how long you slept. Hit 7 hours 50 minutes or more and you keep all 50 points. Below that, points come off increasingly steeply the shorter the night. When a night is detailed enough to show stages, unusually little deep sleep or unusually little REM each cost a further 5 points.',
    },
    {
        name: 'Bedtime',
        max: 30,
        body: 'Consistency. Your bedtime is compared to your typical bedtime over the previous couple of weeks. Going to bed around your usual time, or earlier, keeps all 30 points. Later than usual costs roughly a point for every 5 minutes, and going to bed very early loses a few points too.',
    },
    {
        name: 'Interruptions',
        max: 20,
        body: 'How settled the night was. A few minutes awake is normal and free; beyond about 11 minutes awake you lose roughly a point per 4 minutes, and frequent wake-ups chip away a little more.',
    },
];

const bands = [
    { range: '96–100', label: 'Excellent', color: 'var(--color-score-excellent)' },
    { range: '81–95', label: 'High', color: 'var(--color-score-high)' },
    { range: '61–80', label: 'OK', color: 'var(--color-score-ok)' },
    { range: '41–60', label: 'Low', color: 'var(--color-score-low)' },
    { range: '0–40', label: 'Very low', color: 'var(--color-score-poor)' },
];
</script>

<template>
    <AppHead :og="og" />

    <header>
        <p class="text-eyebrow uppercase text-sleep">Sleep</p>
        <h1 class="mt-1 font-display text-display">How the sleep score works</h1>
    </header>

    <div class="mt-8 max-w-2xl space-y-8">
        <p class="text-body text-neutral-700">
            Every night gets a score out of 100, modelled on the way Apple Health rates sleep. It is the sum of three
            parts: how long you slept, when you went to bed, and how settled the night was. Apple's exact formula is
            private, so this is a close approximation, not a copy of the number in the Health app.
        </p>

        <div class="space-y-6">
            <div v-for="component in components" :key="component.name">
                <div class="flex items-baseline gap-3">
                    <h2 class="text-section">{{ component.name }}</h2>
                    <span class="text-label uppercase text-neutral-500 tnum">up to {{ component.max }} pts</span>
                </div>
                <p class="mt-2 text-body text-neutral-700">{{ component.body }}</p>
            </div>
        </div>

        <div>
            <h2 class="text-section">Bands</h2>
            <div class="mt-3 space-y-2">
                <div v-for="band in bands" :key="band.label" class="flex items-center gap-3 text-meta text-neutral-700">
                    <span class="size-2.5 rounded-full" :style="{ background: band.color }" />
                    <span class="w-20 font-medium">{{ band.label }}</span>
                    <span class="text-neutral-500 tnum">{{ band.range }}</span>
                </div>
            </div>
        </div>

        <p class="text-caption text-neutral-500">
            Scores are calculated once from the recorded sleep stages and stored, so they reflect what was typical for
            you at the time. Because the underlying thresholds are an approximation, the number will sit close to, but
            not exactly on, the one Apple shows.
        </p>
    </div>
</template>
