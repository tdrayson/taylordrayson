<script setup>
import { ref, computed, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowUpRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import Lightbox from '../Components/Overlays/Lightbox.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    photos: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Photos' }],
});

const lightboxIndex = ref(null);

// Masonry via CSS grid row spans: photos stay in document (chronological) order
// so keyboard focus moves across rows, newest first — while each tile spans the
// rows needed for its aspect ratio, giving the staggered masonry look.
const GAP = 12; // matches gap-3
const ROW = 8; // grid-auto-rows base unit

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
    const columns = width >= 1024 ? 4 : width >= 640 ? 3 : 2;
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

function aspect(photo) {
    return photo.width && photo.height ? { aspectRatio: `${photo.width} / ${photo.height}` } : {};
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Photos</h1>
        <p class="mt-2 text-meta text-neutral-500">
            {{ photos.length }} photos from everything I've logged, newest first.
        </p>
    </header>

    <ul
        v-if="photos.length"
        ref="grid"
        class="mt-8 grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
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
                class="block w-full focus:outline-none"
                :aria-label="`View photo from ${photo.caption}, ${photo.date}`"
                @click="lightboxIndex = index"
            >
                <img
                    :src="photo.src"
                    :srcset="photo.srcset || undefined"
                    sizes="(min-width: 1024px) 25vw, (min-width: 640px) 33vw, 50vw"
                    :style="aspect(photo)"
                    alt=""
                    loading="lazy"
                    class="w-full"
                >
                <div class="pointer-events-none absolute inset-x-0 bottom-0 bg-gradient-to-t from-neutral-900/75 to-transparent p-3 pt-8 opacity-0 transition-opacity group-hover/photo:opacity-100 group-focus-within/photo:opacity-100">
                    <p class="truncate text-meta font-medium text-neutral-0">{{ photo.caption }}</p>
                    <p class="text-caption text-neutral-0/80">{{ photo.date }}</p>
                </div>
            </button>
            <Link
                :href="photo.url"
                class="absolute right-2 top-2 flex size-8 items-center justify-center rounded-md bg-neutral-900/55 text-neutral-0 opacity-0 transition-opacity hover:bg-neutral-900/75 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-0 group-hover/photo:opacity-100 group-focus-within/photo:opacity-100"
                :aria-label="`Go to ${photo.caption}`"
            >
                <Icon :icon="ArrowUpRight01Icon" class="size-4" />
            </Link>
        </li>
    </ul>

    <p v-else class="mt-8 text-meta text-neutral-500">No photos yet.</p>

    <Lightbox v-model:index="lightboxIndex" :photos="photos" />
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
