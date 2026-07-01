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

function formatClock(milliseconds) {
    return new Date(milliseconds).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
}

/** Shared timeline bounds so segments and the cursor map the same x-axis. */
const bounds = computed(() => {
    const parsed = props.stages
        .map((segment) => ({
            meta: STAGE_META[segment.stage] ?? { lane: 2, label: segment.stage, color: 'var(--color-neutral-500)' },
            start: new Date(segment.start).getTime(),
            end: new Date(segment.end).getTime(),
        }))
        .filter((segment) => !Number.isNaN(segment.start) && segment.end > segment.start);

    if (parsed.length === 0) {
        return null;
    }

    const from = Math.min(...parsed.map((segment) => segment.start));
    const to = Math.max(...parsed.map((segment) => segment.end));

    return { from, to, span: to - from || 1, parsed };
});

const segments = computed(() => {
    if (!bounds.value) {
        return [];
    }

    const { from, span, parsed } = bounds.value;

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

const track = ref(null);
const cursorX = ref(null);

function onMove(event) {
    const rect = track.value.getBoundingClientRect();

    cursorX.value = Math.min(100, Math.max(0, ((event.clientX - rect.left) / rect.width) * 100));
}

// Keep the readout after a touch lifts; only clear when a mouse leaves.
function onLeave(event) {
    if (event.pointerType !== 'touch') {
        cursorX.value = null;
    }
}

/** The stage and clock time under the cursor, snapping to the nearest segment in any gap. */
const cursor = computed(() => {
    if (cursorX.value === null || !bounds.value) {
        return null;
    }

    const x = cursorX.value;
    const active = segments.value.find((segment) => x >= segment.left && x <= segment.left + segment.width)
        ?? segments.value.reduce((nearest, segment) => {
            const distance = Math.abs(segment.left + segment.width / 2 - x);

            return distance < nearest.distance ? { segment, distance } : nearest;
        }, { segment: segments.value[0], distance: Infinity }).segment;

    return {
        x,
        label: active.label,
        color: active.color,
        duration: active.duration,
        time: formatClock(bounds.value.from + (x / 100) * bounds.value.span),
    };
});
</script>

<template>
    <div class="overflow-x-clip">
        <div
            ref="track"
            class="relative h-24 w-full touch-pan-y select-none"
            @pointerdown="onMove"
            @pointermove="onMove"
            @pointerleave="onLeave"
        >
            <span
                v-for="lane in LANE_COUNT"
                :key="`lane-${lane}`"
                class="absolute inset-x-0 h-px bg-neutral-50"
                :style="{ top: `${(lane - 0.5) * (100 / LANE_COUNT)}%` }"
            />

            <button
                v-for="(segment, index) in segments"
                :key="index"
                type="button"
                class="hypnogram-seg absolute rounded-sm transition-opacity hover:opacity-80"
                :style="{
                    left: `${segment.left}%`,
                    width: `max(2px, ${segment.width}%)`,
                    top: `${segment.lane * (100 / LANE_COUNT) + 2}%`,
                    height: `${100 / LANE_COUNT - 4}%`,
                    background: segment.color,
                    animationDelay: `${(segment.left / 100) * 0.5}s`,
                }"
                :aria-label="`${segment.label} ${segment.duration}`"
            />

            <div
                v-if="cursor"
                class="pointer-events-none absolute inset-y-0 z-10 w-px -translate-x-1/2 bg-neutral-900/30"
                :style="{ left: `${cursor.x}%` }"
            />

            <div
                v-if="cursor"
                class="pointer-events-none absolute -top-2 z-10 flex -translate-x-1/2 -translate-y-full items-center gap-1.5 whitespace-nowrap rounded-md bg-neutral-900 px-2 py-1 text-xs font-medium text-neutral-0 shadow-card"
                :style="{ left: `${Math.min(90, Math.max(10, cursor.x))}%` }"
            >
                <span class="size-2 rounded-full" :style="{ background: cursor.color }" />
                <span class="tnum">{{ cursor.time }}</span>
                <span class="text-neutral-200">{{ cursor.label }}</span>
                <span class="text-neutral-300 tnum">{{ cursor.duration }}</span>
            </div>
        </div>

        <div class="mt-4 flex flex-wrap gap-x-6 gap-y-2">
            <div v-for="total in totals" :key="total.label" class="flex items-center gap-2 text-meta text-neutral-700">
                <span class="size-2.5 rounded-full" :style="{ background: total.color }" />
                {{ total.label }} <span class="text-neutral-500 tnum">{{ total.duration }}</span>
            </div>
        </div>
    </div>
</template>
