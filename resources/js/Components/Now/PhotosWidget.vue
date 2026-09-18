<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    title: { type: String, default: 'Life lately' },
    subtitle: { type: String, default: 'Proof I leave the house' },
    // Each photo: { src? }. A photo without a src shows as a blank placeholder card.
    photos: { type: Array, default: () => Array.from({ length: 6 }, () => ({})) },
    fill: { type: Boolean, default: false },
});

const deckEl = ref(null);
const topIndex = ref(0);
let cleanup = null;

const onImgError = (event) => {
    event.target.style.opacity = 0;
};

onMounted(() => {
    const deck = deckEl.value;
    if (!deck) {
        return;
    }

    const cards = [...deck.querySelectorAll('[data-photo-card]')];
    const jit = [2, -3, 1, -2, 3, -1];
    cards.forEach((card, i) => {
        card.dataset.j = jit[i % jit.length];
        card.dataset.i = i;
    });

    let order = cards.slice();
    const MAXV = 3;
    const EASE = 'transform .42s cubic-bezier(.2,.8,.2,1), opacity .42s';
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    function place(card, depth, anim) {
        card.style.transition = anim && !reduceMotion ? EASE : 'none';
        const j = +card.dataset.j;
        if (depth === 0) {
            card.style.transform = `translate(0, 0) scale(1) rotate(${j * 0.3}deg)`;
            card.style.opacity = 1;
            card.style.zIndex = order.length;
        } else {
            const d = Math.min(depth, MAXV);
            card.style.transform = `translate(${j * 6 * d}px, ${-d * 5}px) scale(${1 - d * 0.05}) rotate(${j * 2.4}deg)`;
            card.style.opacity = depth > MAXV ? 0 : 1;
            card.style.zIndex = order.length - depth;
        }
    }

    function layout(anim, except) {
        order.forEach((card, depth) => {
            if (card !== except) {
                place(card, depth, anim);
            }
        });
        topIndex.value = +order[0].dataset.i;
    }

    layout(false);

    let busy = false;
    let finalizeTimer = null;
    function commit(dir) {
        if (busy) {
            return;
        }
        if (reduceMotion) {
            order.push(order.shift());
            layout(false);
            return;
        }
        busy = true;
        const top = order[0];
        order.push(order.shift());
        top.style.transition = 'transform .45s ease, opacity .45s ease';
        top.style.transform = `translate(${dir * 150}%, -6%) rotate(${dir * 20}deg)`;
        top.style.opacity = 0;
        layout(true, top);
        // Finalize on a timer rather than transitionend: an interrupted
        // transition never fires transitionend, which would leave the deck
        // stuck with busy = true.
        finalizeTimer = window.setTimeout(() => {
            place(top, order.indexOf(top), false);
            busy = false;
        }, 470);
    }

    let dragging = false;
    let cur = null;
    let sx = 0;
    let sy = 0;
    let dx = 0;
    let dy = 0;

    const onDown = (e) => {
        if (busy) {
            return;
        }
        cur = order[0];
        dragging = true;
        sx = e.clientX;
        sy = e.clientY;
        dx = 0;
        dy = 0;
        cur.style.transition = 'none';
        deck.setPointerCapture(e.pointerId);
    };

    const onMove = (e) => {
        if (!dragging) {
            return;
        }
        dx = e.clientX - sx;
        dy = e.clientY - sy;
        const j = +cur.dataset.j;
        cur.style.transform = `translate(${dx}px, ${dy * 0.4}px) rotate(${j * 0.3 + dx * 0.05}deg)`;
        const next = order[1];
        if (next) {
            const p = Math.min(Math.abs(dx) / 120, 1);
            const nj = +next.dataset.j;
            const tx = nj * 6 * (1 - p);
            const ty = -5 * (1 - p);
            const ts = 0.95 + 0.05 * p;
            const tr = nj * 2.4 + (nj * 0.3 - nj * 2.4) * p;
            next.style.transition = 'none';
            next.style.transform = `translate(${tx}px, ${ty}px) scale(${ts}) rotate(${tr}deg)`;
        }
    };

    const end = () => {
        if (!dragging) {
            return;
        }
        dragging = false;
        if (Math.abs(dx) > 70) {
            commit(dx > 0 ? 1 : -1);
        } else if (Math.abs(dx) < 6 && Math.abs(dy) < 6) {
            commit(1);
        } else {
            layout(true);
        }
    };

    // Keyboard equivalent of the swipe: left/right arrows advance the deck.
    const onKey = (e) => {
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
            e.preventDefault();
            commit(1);
        } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
            e.preventDefault();
            commit(-1);
        }
    };

    deck.addEventListener('pointerdown', onDown);
    deck.addEventListener('pointermove', onMove);
    deck.addEventListener('pointerup', end);
    deck.addEventListener('pointercancel', end);
    deck.addEventListener('keydown', onKey);

    cleanup = () => {
        if (finalizeTimer) {
            window.clearTimeout(finalizeTimer);
        }
        deck.removeEventListener('pointerdown', onDown);
        deck.removeEventListener('pointermove', onMove);
        deck.removeEventListener('pointerup', end);
        deck.removeEventListener('pointercancel', end);
        deck.removeEventListener('keydown', onKey);
    };
});

onBeforeUnmount(() => {
    if (cleanup) {
        cleanup();
    }
});
</script>

<template>
    <div class="@container relative flex flex-col overflow-hidden rounded-3xl bg-neutral-0 shadow-card" :class="{ 'aspect-square': !fill }">
        <div class="relative z-4 flex-none bg-neutral-0 px-5 pt-5 pb-7.5 @sm:px-6.5 @sm:pt-6 @sm:pb-9.5 @md:px-8 @md:pt-7.5 @md:pb-11.5 @xl:px-11 @xl:pt-10 @xl:pb-15">
            <div class="flex items-end justify-between gap-2 @sm:gap-2.5 @md:gap-3 @xl:gap-4">
                <div>
                    <h2 class="text-lg font-extrabold tracking-tight @sm:text-2xl @md:text-3xl @xl:text-4xl">{{ title }}</h2>
                    <div class="text-2xs font-medium text-neutral-500 @sm:text-sm @md:text-base @xl:text-xl">{{ subtitle }}</div>
                </div>
                <Link
                    href="/photos"
                    class="flex items-center gap-1 rounded-xs text-2xs font-bold whitespace-nowrap text-neutral-900 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent-500 @md:gap-1.5 @md:text-sm @xl:gap-2 @xl:text-lg"
                >
                    All photos
                    <Icon class="size-3 @sm:size-3.5 @md:size-4 @xl:size-5" name="ArrowRight01Icon" :stroke-width="2.6" />
                </Link>
            </div>
        </div>

        <div
            ref="deckEl"
            class="relative flex-1 touch-none focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-accent-500"
            tabindex="0"
            role="group"
            aria-roledescription="photo carousel"
            aria-label="Recent photos. Use the left and right arrow keys to browse."
        >
            <div
                v-for="(photo, i) in photos"
                :key="i"
                data-photo-card
                class="absolute inset-x-3/50 top-1 -bottom-10 cursor-grab overflow-hidden rounded-lg border-4 border-white bg-neutral-100 shadow-xl shadow-black/20 will-change-transform backface-hidden @sm:-bottom-13 @sm:rounded-2xl @md:-bottom-16 @md:border-5 @xl:-bottom-21 @xl:rounded-3xl @xl:border-7"
                :class="i === topIndex ? 'active:cursor-grabbing' : 'pointer-events-none'"
            >
                <img v-if="photo.src" class="pointer-events-none block size-full object-cover select-none" :src="photo.src" alt="" draggable="false" @error="onImgError" />
            </div>
            <div class="pointer-events-none absolute inset-x-0 bottom-2.5 z-6 flex justify-center gap-1 @sm:bottom-3 @md:bottom-4 @md:gap-1.5 @xl:bottom-5 @xl:gap-2">
                <span
                    v-for="(photo, i) in photos"
                    :key="`dot-${i}`"
                    class="h-1 rounded-full shadow-sm shadow-black/35 transition-all duration-300 @md:h-1.5 @xl:h-2"
                    :class="i === topIndex ? 'w-3 bg-white @sm:w-3.5 @md:w-4 @xl:w-6' : 'w-1 bg-white/55 @md:w-1.5 @xl:w-2'"
                />
            </div>
        </div>
    </div>
</template>
