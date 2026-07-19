<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Chart } from '../../lib/chart.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    type: { type: String, required: true },
    data: { type: Object, required: true },
    options: { type: Object, default: () => ({}) },
    label: { type: String, default: null },
    // A plain-language summary so the chart isn't opaque to screen readers.
    summary: { type: String, default: null },
    height: { type: Number, default: 240 },
});

const canvas = ref(null);
const { resolved } = useTheme();
let chart = null;
let observer = null;

// Destroy any existing chart instance and construct a fresh one. Chart.js
// resolves scriptable colour options (see lib/chart.js) at construction
// time, so recreating the instance is what actually applies the current
// theme's palette rather than reusing the previous draw.
function rebuildChart() {
    if (!canvas.value) {
        return;
    }

    chart?.destroy();
    chart = new Chart(canvas.value, { type: props.type, data: props.data, options: props.options });
}

onMounted(() => {
    // Render once the chart scrolls into view so its draw animation is seen.
    observer = new IntersectionObserver(
        (entries) => {
            if (entries.some((entry) => entry.isIntersecting)) {
                rebuildChart();
                observer.disconnect();
                observer = null;
            }
        },
        { rootMargin: '0px 0px -10% 0px' },
    );

    observer.observe(canvas.value);
});

watch(() => [props.data, props.options], () => chart && rebuildChart(), { deep: true });

// Rebuild on theme toggle so the scriptable palette in lib/chart.js is
// re-resolved against the newly active tokens. Registered synchronously here
// (no await above it) so Vue can auto-dispose it on unmount; guarded so it's
// a no-op if the chart hasn't been created yet (still off-screen).
watch(resolved, () => chart && rebuildChart());

onBeforeUnmount(() => {
    observer?.disconnect();
    chart?.destroy();
});
</script>

<template>
    <figure class="my-7">
        <figcaption v-if="label" class="mb-3 text-label uppercase text-neutral-500">{{ label }}</figcaption>
        <div class="relative w-full" :style="{ height: `${height}px` }">
            <canvas ref="canvas" role="img" :aria-label="summary || label || 'Chart'" />
        </div>
    </figure>
</template>
