<script setup>
import { ref, onMounted, onBeforeUnmount, markRaw } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import { GridStack } from 'gridstack';
import 'gridstack/dist/gridstack.min.css';
import { DragDropVerticalIcon, Tick02Icon, RefreshIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../Layouts/AppLayout.vue';
import { setupWidgetTilt } from '../lib/widgetTilt.js';
import Button from '../Components/Ui/Button.vue';
import Icon from '../Components/Ui/Icon.vue';
import ChargingWidget from '../Components/Now/widgets/ChargingWidget.vue';
import ActivityWidget from '../Components/Now/widgets/ActivityWidget.vue';
import WeatherWidget from '../Components/Now/widgets/WeatherWidget.vue';
import PhotosWidget from '../Components/Now/widgets/PhotosWidget.vue';
import TimeWidget from '../Components/Now/widgets/TimeWidget.vue';
import SleepWidget from '../Components/Now/widgets/SleepWidget.vue';
import EntriesWidget from '../Components/Now/widgets/EntriesWidget.vue';
import ReadingWidget from '../Components/Now/widgets/ReadingWidget.vue';
import LocationWidget from '../Components/Now/widgets/LocationWidget.vue';
import PodcastWidget from '../Components/Now/widgets/PodcastWidget.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    episode: { type: Object, default: null },
});

setLayoutProps({
    breadcrumb: [{ label: 'Now' }],
});

const LAYOUT_KEY = 'now-layout-v1';

const latelyPhotos = [
    { src: 'https://static.photos/people/640x360/47', gradient: 'linear-gradient(135deg, #d6c2b2, #b89a86)' },
    { src: 'https://static.photos/nature/640x360/204', gradient: 'linear-gradient(135deg, #bcd3e6, #8fb0cf)' },
    { src: 'https://static.photos/travel/640x360/88', gradient: 'linear-gradient(135deg, #d9c7b0, #c2a47e)' },
    { src: 'https://static.photos/food/640x360/15', gradient: 'linear-gradient(135deg, #cfe0cd, #9cc09a)' },
    { src: 'https://static.photos/animals/640x360/33', gradient: 'linear-gradient(135deg, #e6cdd6, #cf9ab0)' },
    { src: 'https://static.photos/sports/640x360/120', gradient: 'linear-gradient(135deg, #c8c4e6, #9a8fd0)' },
];

// The default bento, expressed as a 4-column grid. `x`/`y`/`w`/`h` are grid
// cells; markRaw keeps Vue from making the component definitions reactive.
// `sizes` is unused for now but reserved for future per-widget size presets.
const defaultWidgets = [
    { id: 'time', component: markRaw(TimeWidget), x: 0, y: 0, w: 1, h: 1, props: {} },
    { id: 'location', component: markRaw(LocationWidget), x: 1, y: 0, w: 1, h: 1, props: {} },
    { id: 'activity', component: markRaw(ActivityWidget), x: 2, y: 0, w: 2, h: 1, props: { variant: 'dark', fill: true } },
    { id: 'charging', component: markRaw(ChargingWidget), x: 0, y: 1, w: 1, h: 1, props: { device: 'iPhone', percent: 72, timeLeft: '25 min left', charging: true } },
    { id: 'weather', component: markRaw(WeatherWidget), x: 1, y: 1, w: 1, h: 1, props: { condition: 'hot' } },
    { id: 'photos', component: markRaw(PhotosWidget), x: 2, y: 1, w: 2, h: 2, props: { photos: latelyPhotos } },
    { id: 'sleep', component: markRaw(SleepWidget), x: 0, y: 2, w: 2, h: 1, props: {} },
    { id: 'podcast', component: markRaw(PodcastWidget), x: 0, y: 3, w: 1, h: 1, props: { episode: props.episode } },
    { id: 'entries', component: markRaw(EntriesWidget), x: 1, y: 3, w: 1, h: 1, props: {} },
    { id: 'reading', component: markRaw(ReadingWidget), x: 2, y: 3, w: 2, h: 1, props: { fill: true } },
];

function readSaved() {
    try {
        return JSON.parse(localStorage.getItem(LAYOUT_KEY) ?? 'null');
    } catch {
        return null;
    }
}

// Merge any saved positions over the defaults so the rendered gs-* attributes
// already reflect the visitor's last arrangement before Gridstack initialises.
const saved = readSaved();
const widgets = defaultWidgets.map((widget) => {
    const position = saved?.[widget.id];

    return position ? { ...widget, x: position.x, y: position.y } : widget;
});

const gridEl = ref(null);
const editing = ref(false);
let grid = null;
let mobileQuery = null;
let teardownTilt = null;

// 4 columns on desktop, 2 on narrow screens (matching the old bento breakpoint).
// Handled manually rather than via Gridstack's columnOpts, whose non-matching
// fallback reverts to the 12-column default.
function columnsForViewport() {
    return mobileQuery && mobileQuery.matches ? 2 : 4;
}

function applyColumns() {
    grid?.column(columnsForViewport(), 'list');
}

function saveLayout() {
    if (!grid) {
        return;
    }

    const map = {};
    grid.save(false).forEach((node) => {
        map[node.id] = { x: node.x, y: node.y, w: node.w, h: node.h };
    });

    try {
        localStorage.setItem(LAYOUT_KEY, JSON.stringify(map));
    } catch {
        // Storage unavailable (private mode, quota); arrangement just won't persist.
    }
}

function toggleEdit() {
    editing.value = !editing.value;
    grid?.setStatic(!editing.value);
}

function resetLayout() {
    if (!grid) {
        return;
    }

    grid.batchUpdate();
    defaultWidgets.forEach((widget) => {
        const element = gridEl.value.querySelector(`[gs-id="${widget.id}"]`);

        if (element) {
            grid.update(element, { x: widget.x, y: widget.y, w: widget.w, h: widget.h });
        }
    });
    grid.batchUpdate(false);

    try {
        localStorage.removeItem(LAYOUT_KEY);
    } catch {
        // Ignore storage failures.
    }
}

onMounted(() => {
    mobileQuery = window.matchMedia('(max-width: 1024px)');

    grid = GridStack.init(
        {
            column: columnsForViewport(),
            cellHeight: 'auto', // square cells, matching the bento tiles
            margin: 8,
            animate: true, // smoothly reflow the other widgets as one is dragged
            float: false,
            disableResize: true, // reorder only for now
            staticGrid: true, // no dragging until edit mode is on
            handle: '.grid-stack-item-content',
        },
        gridEl.value,
    );

    grid.on('change', saveLayout);
    teardownTilt = setupWidgetTilt(grid);
    mobileQuery.addEventListener('change', applyColumns);
});

onBeforeUnmount(() => {
    mobileQuery?.removeEventListener('change', applyColumns);
    teardownTilt?.();
    grid?.destroy(false);
    grid = null;
});
</script>

<template>
    <AppHead :og="og" />

    <div class="breakout mx-auto w-full max-w-5xl">
        <header class="flex items-start justify-between gap-4">
            <div>
                <h1 class="font-display text-display">Now</h1>
                <p class="mt-2 text-meta text-neutral-500">A live snapshot of my world, ticking away right this second.</p>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <Button v-if="editing" variant="ghost" size="sm" @click="resetLayout">
                    <Icon :icon="RefreshIcon" class="size-4" />
                    Reset
                </Button>
                <Button :variant="editing ? 'primary' : 'secondary'" size="sm" @click="toggleEdit">
                    <Icon :icon="editing ? Tick02Icon : DragDropVerticalIcon" class="size-4" />
                    {{ editing ? 'Done' : 'Edit layout' }}
                </Button>
            </div>
        </header>

        <div ref="gridEl" class="grid-stack now-grid mt-8" :class="{ 'is-editing': editing }">
            <div
                v-for="widget in widgets"
                :key="widget.id"
                class="grid-stack-item"
                :gs-id="widget.id"
                :gs-x="widget.x"
                :gs-y="widget.y"
                :gs-w="widget.w"
                :gs-h="widget.h"
            >
                <div class="grid-stack-item-content">
                    <component :is="widget.component" v-bind="widget.props" />
                </div>
            </div>
        </div>
    </div>
</template>

<style>
/* Gridstack resets. The selector matches Gridstack's own
   `.grid-stack>.grid-stack-item>.grid-stack-item-content` (3 classes) plus
   `.now-grid` so it wins, otherwise its overflow + chrome clip the widgets. */
.now-grid.grid-stack > .grid-stack-item > .grid-stack-item-content {
    overflow: visible;
    background: transparent;
    border: 0;
}

/* Fill the cell so widgets whose content is absolutely positioned (and so have
   no intrinsic height in fill mode, e.g. the reading card) still get a height. */
.now-grid.grid-stack .grid-stack-item-content > * {
    height: 100%;
}

/* Smooth, eased reflow as widgets shuffle to new cells during a drag. Overrides
   Gridstack's flat 0.3s linear (its own `.grid-stack-animate` rule). The dragged
   tile and the drop placeholder stay instant so they track the pointer/target. */
.now-grid.grid-stack-animate > .grid-stack-item {
    transition:
        left 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        top 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        width 0.32s cubic-bezier(0.22, 1, 0.36, 1),
        height 0.32s cubic-bezier(0.22, 1, 0.36, 1);
}

.now-grid.grid-stack-animate > .grid-stack-item.ui-draggable-dragging,
.now-grid.grid-stack-animate > .grid-stack-item.grid-stack-placeholder {
    transition: none;
}

@media (prefers-reduced-motion: reduce) {
    .now-grid.grid-stack-animate > .grid-stack-item {
        transition: none;
    }
}

/* Drag placeholder: a rounded, light-grey dashed outline matching the cards. */
.now-grid .grid-stack-placeholder > .placeholder-content {
    margin: 0;
    background: transparent;
    border: 2px dashed var(--color-neutral-200);
    border-radius: 1.5rem;
}

/* In edit mode the whole card is a drag handle; block inner clicks so links and
   buttons don't fire mid-drag, and hint that tiles are movable. */
.now-grid.is-editing .grid-stack-item-content {
    cursor: move;
}

.now-grid.is-editing .grid-stack-item-content > * {
    pointer-events: none;
}
</style>

<style scoped>
/* Staggered entrance on the inner content so it never fights Gridstack's
   positioning of the outer item. */
@keyframes widget-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

.now-grid :deep(.grid-stack-item-content) {
    animation: widget-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.now-grid :deep(.grid-stack-item:nth-child(2) .grid-stack-item-content) {
    animation-delay: 0.07s;
}

.now-grid :deep(.grid-stack-item:nth-child(3) .grid-stack-item-content) {
    animation-delay: 0.14s;
}

.now-grid :deep(.grid-stack-item:nth-child(4) .grid-stack-item-content) {
    animation-delay: 0.21s;
}

.now-grid :deep(.grid-stack-item:nth-child(5) .grid-stack-item-content) {
    animation-delay: 0.28s;
}

.now-grid :deep(.grid-stack-item:nth-child(6) .grid-stack-item-content) {
    animation-delay: 0.35s;
}

.now-grid :deep(.grid-stack-item:nth-child(7) .grid-stack-item-content) {
    animation-delay: 0.42s;
}

.now-grid :deep(.grid-stack-item:nth-child(8) .grid-stack-item-content) {
    animation-delay: 0.49s;
}

.now-grid :deep(.grid-stack-item:nth-child(9) .grid-stack-item-content) {
    animation-delay: 0.56s;
}

.now-grid :deep(.grid-stack-item:nth-child(10) .grid-stack-item-content) {
    animation-delay: 0.63s;
}

@media (prefers-reduced-motion: reduce) {
    .now-grid :deep(.grid-stack-item-content) {
        animation: none;
    }
}
</style>
