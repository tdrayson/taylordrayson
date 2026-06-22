<script setup>
import { computed } from 'vue';

const props = defineProps({
    // [{ label: 'Deep', stage: 'deep', seconds: 3720 }]
    segments: { type: Array, required: true },
});

const STAGE_COLORS = {
    awake: 'var(--color-sleep-awake)',
    rem: 'var(--color-sleep-rem)',
    light: 'var(--color-sleep-light)',
    core: 'var(--color-sleep-light)',
    deep: 'var(--color-sleep-deep)',
};

function formatDuration(seconds) {
    const minutes = Math.round(seconds / 60);
    const hours = Math.floor(minutes / 60);

    return hours > 0 ? `${hours}h ${String(minutes % 60).padStart(2, '0')}m` : `${minutes % 60}m`;
}

const total = computed(() => props.segments.reduce((sum, segment) => sum + (segment.seconds || 0), 0));

const items = computed(() =>
    props.segments
        .filter((segment) => segment.seconds > 0)
        .map((segment) => ({
            label: segment.label,
            color: STAGE_COLORS[segment.stage] ?? 'var(--color-ink-3)',
            percent: total.value ? (segment.seconds / total.value) * 100 : 0,
            duration: formatDuration(segment.seconds),
        })),
);
</script>

<template>
    <div>
        <div class="flex h-2.5 overflow-hidden rounded-full">
            <div v-for="(item, index) in items" :key="index" :style="{ width: `${item.percent}%`, background: item.color }" />
        </div>
        <div class="mt-2.5 flex flex-wrap gap-x-4 gap-y-1.5 text-caption text-ink-2">
            <div v-for="(item, index) in items" :key="index" class="flex items-center gap-1.5">
                <span class="size-2 rounded-full" :style="{ background: item.color }" />
                {{ item.label }} <span class="text-ink-3 tnum">{{ item.duration }}</span>
            </div>
        </div>
    </div>
</template>
