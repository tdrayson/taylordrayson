<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import StatGrid from '../Stats/StatGrid.vue';
import SectionHead from '../Ui/SectionHead.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import SleepStages from '../Stats/SleepStages.vue';
import SleepScoreRing from '../Stats/SleepScoreRing.vue';
import StageBar from '../Stats/StageBar.vue';
import { clock } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const hasScore = computed(() => props.entry.score != null);

const stats = computed(() => [
    { label: 'Bedtime', value: clock(props.entry.started_at) },
    { label: 'Woke', value: clock(props.entry.occurred_at) },
]);

const fallbackSegments = computed(() =>
    [
        { label: 'Awake', stage: 'awake', seconds: props.entry.awake },
        { label: 'REM', stage: 'rem', seconds: props.entry.rem },
        { label: 'Light', stage: 'light', seconds: props.entry.core },
        { label: 'Deep', stage: 'deep', seconds: props.entry.deep },
    ].filter((segment) => segment.seconds > 0),
);
</script>

<template>
    <div class="space-y-8">
        <div v-if="hasScore" class="animate-rise space-y-3 motion-reduce:animate-none">
            <Eyebrow as="p" class="text-sleep">Sleep score</Eyebrow>
            <SleepScoreRing
                :score="entry.score"
                :duration-score="entry.duration_score"
                :bedtime-score="entry.bedtime_score"
                :interruption-score="entry.interruption_score"
            />
            <Link href="/sleep-score" class="inline-block text-sm text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900">
                How the score is calculated
            </Link>
        </div>

        <div class="animate-rise motion-reduce:animate-none" :style="{ animationDelay: '0.1s' }">
            <StatGrid :stats="stats" />
        </div>

        <div v-if="entry.stages?.length || fallbackSegments.length" class="animate-rise motion-reduce:animate-none" :style="{ animationDelay: '0.18s' }">
            <SectionHead title="Stages" />
            <SleepStages v-if="entry.stages?.length" :stages="entry.stages" />
            <StageBar v-else :segments="fallbackSegments" />
        </div>
    </div>
</template>
