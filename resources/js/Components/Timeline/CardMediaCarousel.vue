<script setup>
import { ref, computed } from 'vue';
import ZoomButton from '../Ui/ZoomButton.vue';

const props = defineProps({
    // The static location/route map (light) and its dark twin, shown as the
    // last slide. Like the photos, it opens the lightbox.
    map: { type: String, default: null },
    mapDark: { type: String, default: null },
    // Photo gallery in {src, srcset, full} shape; each becomes a slide that
    // opens the lightbox.
    photos: { type: Array, default: () => [] },
});

// Opening a slide bubbles its index up so the parent can drive the shared
// Lightbox, whose items are the photos followed by the map.
const emit = defineEmits(['open']);

const track = ref(null);
// Index of the slide currently snapped into view, for the dot indicators.
const active = ref(0);

// Photos lead and the map trails them: an entry with a photo should open on the
// photo, and one without a photo shows the map anyway. `lightboxIndex` maps a
// slide back to its position in the parent's lightbox array, which is built in
// this same order.
const slides = computed(() => {
    const list = props.photos.map((photo, index) => ({ kind: 'photo', photo, lightboxIndex: index }));

    if (props.map) {
        list.push({ kind: 'map', lightboxIndex: props.photos.length });
    }

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
    <div class="focus-frame relative mt-3 w-full max-w-lg rounded-lg">
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
                <button
                    type="button"
                    class="focus-frame-target group/zoom block size-full cursor-zoom-in transition-opacity hover:opacity-95"
                    :aria-label="slide.kind === 'map' ? 'View map' : 'View photo'"
                    @click="emit('open', slide.lightboxIndex)"
                >
                    <template v-if="slide.kind === 'map'">
                        <img :src="map" alt="" class="size-full object-cover" :class="mapDark ? 'dark:hidden' : ''">
                        <img v-if="mapDark" :src="mapDark" alt="" class="hidden size-full object-cover dark:block">
                    </template>
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
                    class="size-1.5 rounded-full transition-colors focus-visible:outline-white"
                    :class="index === active ? 'bg-white' : 'bg-white/50 hover:bg-white/80'"
                    @click="goTo(index)"
                />
            </div>
        </div>
    </div>
</template>
