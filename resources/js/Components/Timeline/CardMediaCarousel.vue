<script setup>
import { ref, computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import ZoomButton from '../Ui/ZoomButton.vue';
import PhotoTagLayer from '../Subjects/PhotoTagLayer.vue';

const props = defineProps({
    // The static location/route map (light) and its dark twin, shown as the
    // first slide and linking through to the entry.
    map: { type: String, default: null },
    mapDark: { type: String, default: null },
    // Photo gallery in {src, srcset, full} shape; each becomes a slide that
    // opens the lightbox.
    photos: { type: Array, default: () => [] },
    // Entry permalink the map slide links to (photos open the lightbox instead).
    url: { type: String, default: null },
});

// Opening a photo slide bubbles its index up so the parent can drive the
// shared Lightbox (whose items are the photos, map excluded).
const emit = defineEmits(['open']);

function hasTags(photo) {
    return (photo.tags ?? []).some((tag) => tag.role === 'subject');
}

const track = ref(null);
// Index of the slide currently snapped into view, for the dot indicators.
const active = ref(0);

// Slides are the map (when present) followed by each photo. `photoIndex` maps a
// photo slide back to its position in the parent's photos/lightbox array.
const slides = computed(() => {
    const list = props.map ? [{ kind: 'map' }] : [];
    props.photos.forEach((photo, index) => list.push({ kind: 'photo', photo, photoIndex: index }));

    return list;
});

// Derive the active dot from the horizontal scroll offset (each slide is one
// track-width wide, so rounding the ratio gives the snapped index).
function onScroll() {
    const el = track.value;
    if (el) {
        active.value = Math.round(el.scrollLeft / el.clientWidth);
    }
}

// Tapping a dot scrolls its slide into view.
function goTo(index) {
    const el = track.value;
    if (el) {
        el.scrollTo({ left: index * el.clientWidth, behavior: 'smooth' });
    }
}
</script>

<template>
    <div class="relative mt-3 w-full max-w-lg">
        <div
            ref="track"
            class="no-scrollbar flex snap-x snap-mandatory overflow-x-auto rounded-lg"
            @scroll.passive="onScroll"
        >
            <div
                v-for="(slide, index) in slides"
                :key="index"
                class="relative aspect-video w-full shrink-0 snap-center overflow-hidden border border-neutral-50"
            >
                <component
                    :is="url ? Link : 'div'"
                    v-if="slide.kind === 'map'"
                    :href="url || undefined"
                    :tabindex="url ? -1 : undefined"
                    :aria-hidden="url ? 'true' : undefined"
                    class="block size-full"
                >
                    <img :src="map" alt="" class="size-full object-cover" :class="mapDark ? 'dark:hidden' : ''">
                    <img v-if="mapDark" :src="mapDark" alt="" class="hidden size-full object-cover dark:block">
                </component>

                <button
                    v-else
                    type="button"
                    class="group/zoom block size-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500"
                    aria-label="View photo"
                    @click="emit('open', slide.photoIndex)"
                >
                    <!-- Who is in it, on the same hover that offers the zoom,
                         so a card answers it without opening the lightbox. -->
                    <PhotoTagLayer v-if="hasTags(slide.photo)" :photo="slide.photo" static class="size-full">
                        <img :src="slide.photo.src" :srcset="slide.photo.srcset || undefined" sizes="100vw" alt="" class="size-full object-cover">
                    </PhotoTagLayer>
                    <img v-else :src="slide.photo.src" :srcset="slide.photo.srcset || undefined" sizes="100vw" alt="" class="size-full object-cover">
                    <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                        <ZoomButton />
                    </span>
                </button>
            </div>
        </div>

        <!-- Dots overlaid on the image, on a subtle scrim so they read over any
             photo. The wrapper ignores pointer events so it never blocks a swipe;
             only the pill of dots is interactive. -->
        <div v-if="slides.length > 1" class="pointer-events-none absolute inset-x-0 bottom-2 flex justify-center">
            <div class="pointer-events-auto flex items-center gap-1.5 rounded-full bg-black/35 px-2 py-1 backdrop-blur-sm">
                <button
                    v-for="(slide, index) in slides"
                    :key="index"
                    type="button"
                    :aria-label="`Go to slide ${index + 1}`"
                    class="size-1.5 rounded-full transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-white"
                    :class="index === active ? 'bg-white' : 'bg-white/50 hover:bg-white/80'"
                    @click="goTo(index)"
                />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Swipe carousel with no visible scrollbar (the dots convey position). */
.no-scrollbar {
    scrollbar-width: none;
}

.no-scrollbar::-webkit-scrollbar {
    display: none;
}
</style>
