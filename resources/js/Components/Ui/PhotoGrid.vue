<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

// Masonry via CSS grid row spans: photos stay in document order so keyboard
// focus moves across rows in that order, while each tile spans the rows
// needed for its aspect ratio, giving the staggered masonry look.
const GAP = 12; // matches gap-3
const ROW = 8; // grid-auto-rows base unit

// Presets keyed by the desktop (lg) column count: the per-breakpoint counts
// drive the JS row-span math, and `cols` is the matching static Tailwind class
// string (Tailwind needs literal classes, so these are enumerated, not built).
// Smaller breakpoints collapse automatically. Add a key here to support a new
// column count; an unknown `columns` value falls back to 4.
const PRESETS = {
    2: { counts: { base: 1, sm: 2, lg: 2 }, cols: 'grid-cols-1 sm:grid-cols-2' },
    3: { counts: { base: 2, sm: 2, lg: 3 }, cols: 'grid-cols-2 sm:grid-cols-2 lg:grid-cols-3' },
    4: { counts: { base: 2, sm: 3, lg: 4 }, cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-4' },
    5: { counts: { base: 2, sm: 3, lg: 5 }, cols: 'grid-cols-2 sm:grid-cols-3 lg:grid-cols-5' },
    6: { counts: { base: 3, sm: 4, lg: 6 }, cols: 'grid-cols-3 sm:grid-cols-4 lg:grid-cols-6' },
};

const props = defineProps({
    photos: { type: Array, required: true },
    // Desktop column count; smaller screens collapse to fewer columns via the
    // matching preset. Supported: 2-6 (default 4). Unknown values fall back to 4.
    columns: { type: Number, default: 4, validator: (value) => Number.isInteger(value) && value >= 2 && value <= 6 },
});

const emit = defineEmits(['open']);

const preset = computed(() => PRESETS[props.columns] ?? PRESETS[4]);
const columnsByBreakpoint = computed(() => preset.value.counts);
const gridColsClass = computed(() => preset.value.cols);

const grid = ref(null);
const columnWidth = ref(0);

// Scroll-reveal: tiles start hidden and fade + rise as they cross into view,
// lightly staggered within each batch that enters together — so the first
// screenful eases in on load and each new row does the same on scroll.
const revealed = ref(new Set());
const delays = ref({});
let revealObserver = null;

function startReveal() {
    revealObserver = new IntersectionObserver(
        (entries) => {
            let stagger = 0;

            for (const entry of entries) {
                if (!entry.isIntersecting) {
                    continue;
                }

                const index = Number(entry.target.dataset.index);
                delays.value[index] = Math.min(stagger, 8) * 45;
                revealed.value = new Set(revealed.value).add(index);
                revealObserver.unobserve(entry.target);
                stagger++;
            }
        },
        { rootMargin: '0px 0px -8% 0px', threshold: 0.05 },
    );

    for (const tile of grid.value?.children ?? []) {
        revealObserver.observe(tile);
    }
}

function measure() {
    const width = window.innerWidth;
    const { base, sm, lg } = columnsByBreakpoint.value;
    const columns = width >= 1024 ? lg : width >= 640 ? sm : base;
    const gridWidth = grid.value?.clientWidth ?? 0;
    columnWidth.value = gridWidth ? (gridWidth - (columns - 1) * GAP) / columns : 0;
}

onMounted(() => {
    measure();
    window.addEventListener('resize', measure);
    nextTick(startReveal);
});

onBeforeUnmount(() => {
    window.removeEventListener('resize', measure);
    revealObserver?.disconnect();
});

function rowSpan(photo) {
    if (!columnWidth.value || !photo.width || !photo.height) {
        return 28;
    }

    const height = columnWidth.value * (photo.height / photo.width);

    return Math.max(1, Math.round((height + GAP) / (ROW + GAP)));
}
</script>

<template>
    <ul
        v-if="photos.length"
        ref="grid"
        class="grid gap-3"
        :class="gridColsClass"
        style="grid-auto-rows: 8px"
    >
        <li
            v-for="(photo, index) in photos"
            :key="index"
            :data-index="index"
            :style="{ gridRowEnd: `span ${rowSpan(photo)}`, transitionDelay: `${delays[index] ?? 0}ms` }"
            class="tile group/photo relative overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 focus-within:ring-2 focus-within:ring-accent-500"
            :class="{ 'is-revealed': revealed.has(index) }"
        >
            <button
                type="button"
                class="block size-full focus:outline-none"
                :aria-label="`View photo from ${photo.caption}, ${photo.date}`"
                @click="emit('open', index)"
            >
                <!-- object-cover so a slightly-off row span crops a hair rather than
                     leaving dead space below a landscape shot. -->
                <img
                    :src="photo.src"
                    :srcset="photo.srcset || undefined"
                    sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                    :alt="photo.alt || ''"
                    loading="lazy"
                    class="size-full object-cover"
                >
                <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
                <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/75 to-transparent p-3 pt-8 opacity-0 transition-opacity group-hover/photo:opacity-100 group-focus-within/photo:opacity-100">
                    <p class="truncate text-meta font-medium text-white">{{ photo.caption }}</p>
                    <p class="text-caption text-white/80">{{ photo.date }}</p>
                </div>
            </button>
            <Link
                :href="photo.url"
                class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-md bg-black/55 text-white opacity-0 transition-opacity hover:bg-black/75 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white group-hover/photo:opacity-100 group-focus-within/photo:opacity-100"
                :aria-label="`Go to ${photo.caption}`"
            >
                <Icon name="ArrowUpRight01Icon" class="size-4" />
            </Link>
        </li>
    </ul>
</template>

<style scoped>
.tile {
    opacity: 0;
    transform: translateY(12px);
    transition: opacity 0.5s ease, transform 0.5s ease;
}

.tile.is-revealed {
    opacity: 1;
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .tile {
        opacity: 1;
        transform: none;
        transition: none;
    }
}
</style>
