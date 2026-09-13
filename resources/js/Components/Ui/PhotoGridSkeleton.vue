<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import Skeleton from './Skeleton.vue';
import { cn } from '../../lib/cn.js';
import {
    DEFAULT_COLUMNS,
    GAP,
    PLACEHOLDER_RATIOS,
    ROW,
    SQUARE_SPAN,
    preset as presetFor,
    spanForRatio,
} from '../../lib/photoGrid.js';

const props = defineProps({
    count: { type: Number, default: 12 },
    // Mirrors PhotoGrid's, so the placeholder does not reflow when real tiles land.
    columns: { type: Number, default: DEFAULT_COLUMNS },
    class: { type: [String, Array, Object], default: '' },
});

const grid = ref(null);
const columnWidth = ref(0);

// Measured rather than assumed: a span is a row count, so the pixel height it
// produces only matches a photo's shape once the column width is known.
function measure() {
    const { base, sm, lg } = presetFor(props.columns).counts;
    const columns = window.innerWidth >= 1024 ? lg : window.innerWidth >= 640 ? sm : base;
    const width = grid.value?.clientWidth ?? 0;

    columnWidth.value = width ? (width - (columns - 1) * GAP) / columns : 0;
}

onMounted(() => {
    measure();
    window.addEventListener('resize', measure);
});

onBeforeUnmount(() => window.removeEventListener('resize', measure));

const spans = computed(() =>
    Array.from({ length: props.count }, (_, index) => {
        const ratio = PLACEHOLDER_RATIOS[index % PLACEHOLDER_RATIOS.length];

        // Before the first measure, a square tile is the least wrong guess.
        return columnWidth.value ? spanForRatio(columnWidth.value, ratio) : SQUARE_SPAN;
    }),
);

const classes = computed(() => cn('grid gap-3', presetFor(props.columns).cols, props.class));
</script>

<template>
    <div ref="grid" :class="classes" :style="{ gridAutoRows: `${ROW}px` }">
        <Skeleton
            v-for="(span, index) in spans"
            :key="index"
            variant="card"
            :style="{ gridRowEnd: `span ${span}` }"
        />
    </div>
</template>
