<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { projectPoints } from '../../lib/geo.js';

const props = defineProps({
    points: { type: Array, required: true }, // [[lng, lat], ...]
    color: { type: String, default: 'var(--color-accent)' },
    endpoints: { type: Boolean, default: false },
});

const container = ref(null);
const size = ref({ width: 0, height: 0 });
let observer = null;

const projected = computed(() => projectPoints(props.points, size.value.width, size.value.height));
const path = computed(() => projected.value.map((point) => point.join(',')).join(' '));
const ends = computed(() => (projected.value.length ? [projected.value[0], projected.value.at(-1)] : []));

onMounted(() => {
    observer = new ResizeObserver(([entry]) => {
        size.value = { width: entry.contentRect.width, height: entry.contentRect.height };
    });
    observer.observe(container.value);
});

onBeforeUnmount(() => observer?.disconnect());
</script>

<template>
    <div ref="container" class="relative h-40 w-full overflow-hidden rounded-lg border border-line-2 bg-surface">
        <svg v-if="size.width && projected.length" class="size-full" :viewBox="`0 0 ${size.width} ${size.height}`" fill="none">
            <polyline
                :points="path"
                :style="{ stroke: color }"
                stroke-width="2.5"
                stroke-linejoin="round"
                stroke-linecap="round"
            />
            <circle
                v-for="(point, index) in (endpoints ? ends : [])"
                :key="index"
                :cx="point[0]"
                :cy="point[1]"
                r="4"
                fill="#ffffff"
                :style="{ stroke: color }"
                stroke-width="3"
            />
        </svg>
    </div>
</template>
