<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import { DEFAULT_COLUMNS, ROW, preset as presetFor } from '../../lib/photoGrid.js';

const props = defineProps({
    // 'block' is a single bar sized by class. The composed variants stand in for
    // a specific deferred payload: 'feed' for a stack of date-grouped entries,
    // 'photos' for the masonry grid.
    variant: { type: String, default: 'block', validator: (value) => ['block', 'feed', 'photos'].includes(value) },
    // How many repeats a composed variant draws. Ignored by 'block'.
    count: { type: Number, default: 3 },
    // Desktop column count for the 'photos' variant; mirrors PhotoGrid's, so
    // the placeholder grid does not reflow when the real tiles land.
    columns: { type: Number, default: DEFAULT_COLUMNS },
    class: { type: [String, Array, Object], default: '' },
});

// Varied spans so the placeholder grid staggers like the real masonry rather
// than reading as a uniform table. Cycled, so any count keeps the rhythm.
const PHOTO_SPANS = [24, 34, 28, 40, 30, 22, 36, 26];

const photoSpans = computed(() =>
    Array.from({ length: props.count }, (_, index) => PHOTO_SPANS[index % PHOTO_SPANS.length]),
);

const gridColsClass = computed(() => presetFor(props.columns).cols);

const block = 'animate-pulse rounded-md bg-neutral-25 motion-reduce:animate-none';
</script>

<template>
    <!-- aria-hidden throughout: the wait is announced by the region these fill,
         and a screen reader has nothing to gain from the placeholder shapes. -->
    <div v-if="variant === 'feed'" :class="cn('space-y-6', props.class)" aria-hidden="true">
        <div v-for="i in count" :key="i" class="space-y-3">
            <div :class="cn(block, 'h-6 w-48')" />
            <div :class="cn(block, 'h-24 rounded-lg')" />
        </div>
    </div>

    <ul
        v-else-if="variant === 'photos'"
        :class="cn('grid gap-3', gridColsClass, props.class)"
        :style="{ gridAutoRows: `${ROW}px` }"
        aria-hidden="true"
    >
        <li
            v-for="(span, index) in photoSpans"
            :key="index"
            :style="{ gridRowEnd: `span ${span}` }"
            :class="cn(block, 'rounded-lg')"
        />
    </ul>

    <div v-else :class="cn(block, props.class)" aria-hidden="true" />
</template>
