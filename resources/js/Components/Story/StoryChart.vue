<script setup>
import { ref, onMounted, onBeforeUnmount, watch } from 'vue';
import { Chart } from '../../lib/storyChart.js';

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
let chart = null;
let observer = null;

function render() {
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
                render();
                observer.disconnect();
                observer = null;
            }
        },
        { rootMargin: '0px 0px -10% 0px' },
    );

    observer.observe(canvas.value);
});

watch(() => [props.data, props.options], () => chart && render(), { deep: true });

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
