<script setup>
import { ref, computed, watch, nextTick, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Cancel01Icon, ArrowLeft01Icon, ArrowRight01Icon, ArrowUpRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    photos: { type: Array, required: true },
    // The index of the open photo, or null when the lightbox is closed.
    index: { type: Number, default: null },
    // Opt-in overlay widgets (each also needs the matching field on the photo).
    caption: { type: Boolean, default: true }, // photo.caption + photo.date
    counter: { type: Boolean, default: true }, // n / total
    link: { type: Boolean, default: true }, // photo.url → entry link
});

const emit = defineEmits(['update:index']);

const isOpen = computed(() => props.index !== null && props.index >= 0 && props.index < props.photos.length);
const current = computed(() => (isOpen.value ? props.photos[props.index] : null));
const hasMultiple = computed(() => props.photos.length > 1);

// Focus management: the element that opened the lightbox, restored on close.
const dialogEl = ref(null);
let lastFocused = null;

function focusableInDialog() {
    if (!dialogEl.value) {
        return [];
    }

    return [...dialogEl.value.querySelectorAll('button, a[href], [tabindex]:not([tabindex="-1"])')].filter(
        (el) => !el.hasAttribute('disabled') && el.offsetParent !== null,
    );
}

function close() {
    emit('update:index', null);
}

function step(delta) {
    emit('update:index', (props.index + delta + props.photos.length) % props.photos.length);
}

// Warm the browser cache for the neighbouring full-size images so stepping
// between them is instant.
function preloadNeighbours(idx) {
    if (idx === null || typeof window === 'undefined') {
        return;
    }

    for (const offset of [1, -1, 2, -2]) {
        const photo = props.photos[(idx + offset + props.photos.length) % props.photos.length];

        if (photo?.full) {
            const image = new Image();
            image.src = photo.full;
        }
    }
}

function onKeydown(event) {
    if (!isOpen.value) {
        return;
    }

    if (event.key === 'Escape') {
        close();
    } else if (event.key === 'ArrowLeft') {
        slideTo(-1);
    } else if (event.key === 'ArrowRight') {
        slideTo(1);
    } else if (event.key === 'Tab') {
        // Trap focus inside the dialog.
        const focusable = focusableInDialog();

        if (focusable.length === 0) {
            event.preventDefault();

            return;
        }

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        const active = document.activeElement;

        if (event.shiftKey && (active === first || !dialogEl.value.contains(active))) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && active === last) {
            event.preventDefault();
            first.focus();
        }
    }
}

// Drag / swipe carousel. Three slides (previous, current, next) ride in a track;
// the track follows the pointer, then animates fully to the neighbour past a
// threshold (or snaps back). After the slide settles we swap the index and
// recentre with no transition, so the same image stays put. Works for mouse and
// touch via pointer events.
const SWIPE_THRESHOLD = 48;
const SLIDE = 100 / 3; // each slide is a third of the 300%-wide track

const dragPx = ref(0);
const extra = ref(0); // 0 at rest, -SLIDE animating to next, +SLIDE to previous
const animating = ref(false);
let startX = 0;
let startY = 0;
let dragging = false;
let didDrag = false;
let settleTimer = null;
let pendingStep = 0;

const wrap = (offset) => props.photos[(props.index + offset + props.photos.length) % props.photos.length];

// The three slides centred on the current photo.
const slides = computed(() => (isOpen.value ? [wrap(-1), wrap(0), wrap(1)] : []));

const trackStyle = computed(() => ({
    width: '300%',
    transform: `translateX(calc(${-SLIDE + extra.value}% + ${dragPx.value}px))`,
    transition: animating.value ? 'transform 0.3s ease' : 'none',
}));

// Apply a pending slide once its animation finishes: swap the index and recentre
// instantly so the just-revealed image becomes the new current without a jump.
function settle() {
    clearTimeout(settleTimer);

    if (pendingStep !== 0) {
        animating.value = false;
        extra.value = 0;
        step(pendingStep);
        pendingStep = 0;
    } else {
        animating.value = false;
    }
}

// Animate a slide to the neighbour, then settle (used by the arrows, keyboard,
// and a drag past the threshold) so every navigation shares one animation.
function slideTo(direction) {
    if (!hasMultiple.value) {
        return;
    }

    settle(); // finish any in-flight slide first
    dragPx.value = 0;
    animating.value = true;
    extra.value = direction > 0 ? -SLIDE : SLIDE;
    pendingStep = direction;
    settleTimer = setTimeout(settle, 300);
}

function onPointerDown(event) {
    if (event.button !== undefined && event.button !== 0) {
        return;
    }

    settle(); // finish any in-flight slide before starting a new drag
    startX = event.clientX;
    startY = event.clientY;
    dragging = true;
    animating.value = false;
    didDrag = false;
}

function onPointerMove(event) {
    if (!dragging) {
        return;
    }

    const delta = event.clientX - startX;

    if (Math.abs(delta) > 5) {
        didDrag = true;
    }

    // Only a multi-photo lightbox is a draggable carousel.
    if (hasMultiple.value) {
        dragPx.value = delta;
    }
}

function onPointerUp(event) {
    if (!dragging) {
        return;
    }

    dragging = false;
    const deltaX = event.clientX - startX;
    const deltaY = event.clientY - startY;

    // A downward drag closes (touch-friendly).
    if (deltaY > SWIPE_THRESHOLD && Math.abs(deltaY) > Math.abs(deltaX)) {
        dragPx.value = 0;
        close();

        return;
    }

    if (hasMultiple.value && deltaX < -SWIPE_THRESHOLD) {
        slideTo(1);
    } else if (hasMultiple.value && deltaX > SWIPE_THRESHOLD) {
        slideTo(-1);
    } else {
        // Snap back to centre.
        dragPx.value = 0;
        extra.value = 0;
        pendingStep = 0;
        animating.value = true;
        settleTimer = setTimeout(settle, 300);
    }
}

function onPointerCancel() {
    if (!dragging) {
        return;
    }

    dragging = false;
    dragPx.value = 0;
    extra.value = 0;
    pendingStep = 0;
    animating.value = true;
    settleTimer = setTimeout(settle, 300);
}

// Close on a click in the empty area, unless that click concluded a drag.
function closeUnlessDrag() {
    if (didDrag) {
        didDrag = false;

        return;
    }

    close();
}

watch(isOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
    clearTimeout(settleTimer);
    dragPx.value = 0;
    extra.value = 0;
    animating.value = false;
    dragging = false;
    pendingStep = 0;

    if (open) {
        lastFocused = document.activeElement;
        document.addEventListener('keydown', onKeydown);
        nextTick(() => {
            const focusable = focusableInDialog();
            (focusable[0] ?? dialogEl.value)?.focus();
        });
    } else {
        document.removeEventListener('keydown', onKeydown);
        // Restore focus to whatever opened the lightbox.
        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }

        lastFocused = null;
    }
});

// Preload neighbours whenever the open photo changes.
watch(() => props.index, (idx) => preloadNeighbours(idx));

onBeforeUnmount(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="lightbox">
            <div
                v-if="isOpen"
                ref="dialogEl"
                tabindex="-1"
                class="fixed inset-0 z-50 flex flex-col gap-3 p-3 focus:outline-none sm:p-5"
                role="dialog"
                aria-modal="true"
                aria-label="Photo viewer"
                @pointerdown="onPointerDown"
                @pointermove="onPointerMove"
                @pointerup="onPointerUp"
                @pointercancel="onPointerCancel"
            >
                <div class="absolute inset-0 bg-neutral-900/95" @click="closeUnlessDrag" />

                <!-- Top bar: entry link and close. -->
                <div class="relative flex shrink-0 items-center justify-between gap-3">
                    <Link
                        v-if="link && current?.url"
                        :href="current.url"
                        class="flex items-center gap-1.5 rounded-full bg-neutral-0/10 py-2 pl-4 pr-3 text-meta text-neutral-0 transition-colors hover:bg-neutral-0/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-0"
                        :aria-label="current?.caption ? `View ${current.caption}` : current?.date ? `View entry from ${current.date}` : 'View entry'"
                    >
                        <span>View entry</span>
                        <Icon :icon="ArrowUpRight01Icon" class="size-4" />
                    </Link>
                    <span v-else />

                    <button
                        type="button"
                        class="flex size-10 items-center justify-center rounded-full bg-neutral-0/10 text-neutral-0 transition-colors hover:bg-neutral-0/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-0"
                        aria-label="Close"
                        @click="close"
                    >
                        <Icon :icon="Cancel01Icon" class="size-5" />
                    </button>
                </div>

                <!-- Image region: fills the space between the bars; clicking the
                     empty area around the image closes. -->
                <div
                    class="relative flex min-h-0 flex-1 touch-none overflow-hidden"
                    :class="hasMultiple ? 'cursor-grab active:cursor-grabbing' : 'items-center justify-center'"
                    @click.self="closeUnlessDrag"
                >
                    <!-- Multiple photos: a draggable three-slide carousel. -->
                    <div v-if="hasMultiple" class="flex h-full shrink-0" :style="trackStyle">
                        <div
                            v-for="(slide, slideIndex) in slides"
                            :key="slideIndex"
                            class="flex h-full w-1/3 shrink-0 items-center justify-center"
                            @click.self="closeUnlessDrag"
                        >
                            <img :src="slide.full" draggable="false" alt="" class="max-h-full max-w-full select-none rounded-lg object-contain shadow-card">
                        </div>
                    </div>

                    <!-- Single photo: a static, non-draggable image. -->
                    <img v-else-if="current" :src="current.full" draggable="false" alt="" class="max-h-full max-w-full select-none rounded-lg object-contain shadow-card">

                    <button
                        v-if="hasMultiple"
                        type="button"
                        class="absolute left-0 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-0/10 text-neutral-0 transition-colors hover:bg-neutral-0/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-0"
                        aria-label="Previous photo"
                        @click="slideTo(-1)"
                    >
                        <Icon :icon="ArrowLeft01Icon" class="size-5" />
                    </button>
                    <button
                        v-if="hasMultiple"
                        type="button"
                        class="absolute right-0 top-1/2 flex size-10 -translate-y-1/2 items-center justify-center rounded-full bg-neutral-0/10 text-neutral-0 transition-colors hover:bg-neutral-0/20 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-neutral-0"
                        aria-label="Next photo"
                        @click="slideTo(1)"
                    >
                        <Icon :icon="ArrowRight01Icon" class="size-5" />
                    </button>
                </div>

                <!-- Bottom bar: caption, date, position (each opt-in). -->
                <div
                    v-if="(caption && current?.caption) || (counter && hasMultiple)"
                    class="relative flex shrink-0 flex-col items-center gap-0.5 text-center"
                >
                    <p v-if="caption && current?.caption" class="max-w-reading truncate text-meta font-medium text-neutral-0">{{ current.caption }}</p>
                    <p v-if="caption && current?.date" class="text-caption text-neutral-0/70">{{ current.date }}</p>
                    <span v-if="counter && hasMultiple" class="mt-1 text-caption text-neutral-0/60 tnum">{{ index + 1 }} / {{ photos.length }}</span>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.lightbox-enter-active,
.lightbox-leave-active {
    transition: opacity 0.2s ease;
}

.lightbox-enter-from,
.lightbox-leave-to {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .lightbox-enter-active,
    .lightbox-leave-active {
        transition: none;
    }
}
</style>
