<script setup>
import { computed, ref } from 'vue';

const props = defineProps({
    stages: { type: Array, required: true },
});

/** Lane order top → bottom, plus label + colour per stage. `light` is an alias for `core`. */
const STAGE_META = {
    awake: { lane: 0, label: 'Awake', color: 'var(--color-sleep-awake)' },
    rem: { lane: 1, label: 'REM', color: 'var(--color-sleep-rem)' },
    core: { lane: 2, label: 'Core', color: 'var(--color-sleep-light)' },
    light: { lane: 2, label: 'Core', color: 'var(--color-sleep-light)' },
    deep: { lane: 3, label: 'Deep', color: 'var(--color-sleep-deep)' },
};

const LANE_COUNT = 4;

function formatDuration(seconds) {
    const minutes = Math.round(seconds / 60);

    return `${Math.floor(minutes / 60)}h ${minutes % 60}m`;
}

const segments = computed(() => {
    const parsed = props.stages
        .map((segment) => ({
            meta: STAGE_META[segment.stage] ?? { lane: 2, label: segment.stage, color: 'var(--color-ink-3)' },
            start: new Date(segment.start).getTime(),
            end: new Date(segment.end).getTime(),
        }))
        .filter((segment) => !Number.isNaN(segment.start) && segment.end > segment.start);

    if (parsed.length === 0) {
        return [];
    }

    const from = Math.min(...parsed.map((segment) => segment.start));
    const to = Math.max(...parsed.map((segment) => segment.end));
    const span = to - from || 1;

    return parsed.map((segment) => ({
        left: ((segment.start - from) / span) * 100,
        width: ((segment.end - segment.start) / span) * 100,
        lane: segment.meta.lane,
        color: segment.meta.color,
        label: segment.meta.label,
        duration: formatDuration((segment.end - segment.start) / 1000),
    }));
});

/** One legend entry per distinct stage, with its summed duration. */
const totals = computed(() => {
    const byLane = new Map();

    props.stages.forEach((segment) => {
        const meta = STAGE_META[segment.stage];
        const seconds = (new Date(segment.end).getTime() - new Date(segment.start).getTime()) / 1000;

        if (!meta || Number.isNaN(seconds) || seconds <= 0) {
            return;
        }

        const current = byLane.get(meta.lane) ?? { ...meta, seconds: 0 };
        current.seconds += seconds;
        byLane.set(meta.lane, current);
    });

    return [...byLane.values()]
        .sort((a, b) => a.lane - b.lane)
        .map((entry) => ({ label: entry.label, color: entry.color, duration: formatDuration(entry.seconds) }));
});

const hovered = ref(null);
</script>

<template>
    <div>
        <div class="relative h-24 w-full">
            <span
                v-for="lane in LANE_COUNT"
                :key="`lane-${lane}`"
                class="absolute inset-x-0 h-px bg-line-2"
                :style="{ top: `${(lane - 0.5) * (100 / LANE_COUNT)}%` }"
            />

            <button
                v-for="(segment, index) in segments"
                :key="index"
                type="button"
                class="absolute rounded-sm transition-opacity hover:opacity-80"
                :style="{
                    left: `${segment.left}%`,
                    width: `max(2px, ${segment.width}%)`,
                    top: `${segment.lane * (100 / LANE_COUNT) + 2}%`,
                    height: `${100 / LANE_COUNT - 4}%`,
                    background: segment.color,
                }"
                :aria-label="`${segment.label} ${segment.duration}`"
                @mouseenter="hovered = index"
                @mouseleave="hovered = null"
                @focus="hovered = index"
                @blur="hovered = null"
            />

            <div
                v-if="hovered !== null"
                class="pointer-events-none absolute z-10 -translate-x-1/2 -translate-y-2 whitespace-nowrap rounded-md bg-ink px-2 py-1 text-xs font-medium text-canvas shadow-card"
                :style="{
                    left: `${Math.min(92, Math.max(8, segments[hovered].left + segments[hovered].width / 2))}%`,
                    top: `${segments[hovered].lane * (100 / LANE_COUNT)}%`,
                }"
            >
                {{ segments[hovered].label }} {{ segments[hovered].duration }}
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
            <div v-for="total in totals" :key="total.label" class="flex items-center gap-2 text-meta text-ink-2">
                <span class="size-2.5 rounded-full" :style="{ background: total.color }" />
                {{ total.label }} <span class="text-ink-3 tnum">{{ total.duration }}</span>
            </div>
        </div>
    </div>
</template>
