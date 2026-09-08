<script setup>
import { computed } from 'vue';
import Skeleton from './Skeleton.vue';
import { cn } from '../../lib/cn.js';
import { DEFAULT_COLUMNS, ROW, preset as presetFor } from '../../lib/photoGrid.js';

const props = defineProps({
    count: { type: Number, default: 12 },
    // Mirrors PhotoGrid's, so the placeholder does not reflow when real tiles land.
    columns: { type: Number, default: DEFAULT_COLUMNS },
    class: { type: [String, Array, Object], default: '' },
});

// Varied spans so the placeholder staggers like real masonry rather than
// reading as a uniform table. Cycled, so any count keeps the rhythm.
const SPANS = [24, 34, 28, 40, 30, 22, 36, 26];

const spans = computed(() =>
    Array.from({ length: props.count }, (_, index) => SPANS[index % SPANS.length]),
);

const classes = computed(() => cn('grid gap-3', presetFor(props.columns).cols, props.class));
</script>

<template>
    <div :class="classes" :style="{ gridAutoRows: `${ROW}px` }">
        <Skeleton
            v-for="(span, index) in spans"
            :key="index"
            variant="card"
            :style="{ gridRowEnd: `span ${span}` }"
        />
    </div>
</template>
