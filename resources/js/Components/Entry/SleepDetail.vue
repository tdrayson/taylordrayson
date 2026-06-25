<script setup>
import { computed } from 'vue';
import StatGrid from '../Stats/StatGrid.vue';
import SectionHead from '../Ui/SectionHead.vue';
import SleepStages from '../Stats/SleepStages.vue';
import StageBar from '../Stats/StageBar.vue';
import { time } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const efficiency = computed(() => {
    const inBed = props.entry.duration + (props.entry.awake || 0);

    return inBed > 0 ? `${Math.round((props.entry.duration / inBed) * 100)}%` : null;
});

const stats = computed(() => [
    { label: 'Efficiency', value: efficiency.value },
    { label: 'Bedtime', value: time(props.entry.bedtime) },
    { label: 'Woke', value: time(props.entry.wake_time) },
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
        <StatGrid :stats="stats" />

        <div v-if="entry.stages?.length || fallbackSegments.length">
            <SectionHead title="Stages" />
            <SleepStages v-if="entry.stages?.length" :stages="entry.stages" />
            <StageBar v-else :segments="fallbackSegments" />
        </div>
    </div>
</template>
