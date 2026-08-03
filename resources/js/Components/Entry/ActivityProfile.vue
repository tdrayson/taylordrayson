<script setup>
import { computed } from 'vue';
import SectionHead from '../Ui/SectionHead.vue';
import ProfileChart from '../Stats/ProfileChart.vue';

const props = defineProps({
    // { heart_rate, altitude, speed, track } series of {time, bpm|value|lat/lng}.
    profile: { type: Object, required: true },
    duration: { type: Number, default: null },
    // Shared cursor from useActivityCursor.
    cursor: { type: Object, required: true },
});

// Bare-number series for a stream key, or [] when absent.
function values(key, field) {
    const series = props.profile?.[key];
    return Array.isArray(series) ? series.map((point) => point[field]) : [];
}

const heartRate = computed(() => values('heart_rate', 'bpm'));
const elevation = computed(() => values('altitude', 'value'));
// Stored m/s -> mph for display (British default; a later pass can honour the unit toggle).
const speed = computed(() => values('speed', 'value').map((v) => v * 2.23694));

// A sensor that never moved still streams a full-length series: indoor weight
// training reports speed 0 throughout, an indoor court reports the same
// altitude all session. Charting a constant draws a flat line that says
// nothing, so a series only earns a chart once its values actually vary.
function varies(points) {
    return points.length > 1 && points.some((value) => value !== points[0]);
}

// HR density gate carried over from ActivityDetail: only show a dense-enough trace.
const MIN_HR_POINTS = 5;
const MAX_HR_GAP_SECONDS = 120;
const showHeartRate = computed(() => {
    const points = heartRate.value.length;
    if (points < MIN_HR_POINTS) {
        return false;
    }
    const duration = Number(props.duration) || 0;
    return duration <= 0 || points >= duration / MAX_HR_GAP_SECONDS;
});

// The charts to render, in order, each only when it has data.
// Note: no --color-run/--color-walk/--color-ride tokens exist in app.css, so
// each trace borrows a distinct, already-defined data-type colour instead.
const charts = computed(() =>
    [
        showHeartRate.value ? { key: 'hr', label: 'Heart rate', unit: 'bpm', color: 'var(--color-fuel)', points: heartRate.value } : null,
        varies(elevation.value) ? { key: 'elevation', label: 'Elevation', unit: 'm', color: 'var(--color-activity)', points: elevation.value } : null,
        varies(speed.value) ? { key: 'speed', label: 'Speed', unit: 'mph', color: 'var(--color-flight)', points: speed.value } : null,
    ].filter(Boolean),
);
</script>

<template>
    <div v-if="charts.length" data-testid="activity-profile" class="space-y-6">
        <div v-for="chart in charts" :key="chart.key">
            <SectionHead :title="chart.label" :meta="`${chart.unit} over the activity`" />
            <ProfileChart :points="chart.points" :duration="duration" :color="chart.color" :unit="chart.unit" :cursor="cursor" :fill="chart.key !== 'speed'" />
        </div>
    </div>
</template>
