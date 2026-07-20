<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../../Ui/Icon.vue';

const props = defineProps({
    title: { type: String, default: 'Life lately' },
    subtitle: { type: String, default: 'Proof I leave the house' },
    // Each photo: { src? , gradient }. Gradients act as placeholders/fallbacks.
    photos: {
        type: Array,
        default: () => [
            { gradient: 'linear-gradient(135deg, #d6c2b2, #b89a86)' },
            { gradient: 'linear-gradient(135deg, #bcd3e6, #8fb0cf)' },
            { gradient: 'linear-gradient(135deg, #d9c7b0, #c2a47e)' },
            { gradient: 'linear-gradient(135deg, #cfe0cd, #9cc09a)' },
            { gradient: 'linear-gradient(135deg, #e6cdd6, #cf9ab0)' },
            { gradient: 'linear-gradient(135deg, #c8c4e6, #9a8fd0)' },
        ],
    },
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

    const cards = [...deck.querySelectorAll('.photos__card')];
    const jit = [2, -3, 1, -2, 3, -1];
    cards.forEach((card, i) => {
        card.dataset.j = jit[i % jit.length];
        card.dataset.i = i;
    });

    let order = cards.slice();
    const MAXV = 3;
    const EASE = 'transform .42s cubic-bezier(.2,.8,.2,1), opacity .42s';

    function place(card, depth, anim) {
        card.style.transition = anim ? EASE : 'none';
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
        order.forEach((card, i) => card.classList.toggle('photos__card--top', i === 0));
        topIndex.value = +order[0].dataset.i;
    }

    layout(false);

    let busy = false;
    let finalizeTimer = null;
    function commit(dir) {
        if (busy) {
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
    <div class="photos rounded-3xl" :class="{ 'photos--has-aspect': !fill }">
        <div class="photos__header">
            <div class="photos__header-row">
                <div>
                    <h2 class="photos__title">{{ title }}</h2>
                    <div class="photos__subtitle">{{ subtitle }}</div>
                </div>
                <Link href="/photos" class="photos__link">
                    All photos
                    <Icon class="photos__link-icon" name="ArrowRight01Icon" :stroke-width="2.6" />
                </Link>
            </div>
        </div>

        <div
            ref="deckEl"
            class="photos__deck"
            tabindex="0"
            role="group"
            aria-roledescription="photo carousel"
            aria-label="Recent photos. Use the left and right arrow keys to browse."
        >
            <div v-for="(photo, i) in photos" :key="i" class="photos__card" :style="{ background: photo.gradient || 'var(--color-neutral-100)' }">
                <img v-if="photo.src" :src="photo.src" alt="" @error="onImgError" />
            </div>
            <div class="photos__dots">
                <span v-for="(photo, i) in photos" :key="`dot-${i}`" class="photos__dot" :class="{ 'photos__dot--active': i === topIndex }" />
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 4.52) so the 2x2 card scales with the grid cell. */
.photos {
    container-type: inline-size;
    position: relative;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: var(--color-neutral-0);
    box-shadow: var(--shadow-card);
}

.photos--has-aspect {
    aspect-ratio: 1 / 1;
}

.photos__header {
    flex: none;
    position: relative;
    z-index: 4;
    padding: 6.2cqw 6.6cqw 9.4cqw;
    background: var(--color-neutral-0);
}

.photos__header-row {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 2.6cqw;
}

.photos__title {
    font-family: var(--font-sans);
    font-size: 5.75cqw;
    font-weight: 800;
    letter-spacing: -0.02em;
}

.photos__subtitle {
    font-size: 3.3cqw;
    font-weight: 500;
    color: var(--color-neutral-500);
}

.photos__link {
    display: flex;
    align-items: center;
    gap: 1.1cqw;
    border: none;
    background: none;
    font-size: 2.9cqw;
    font-weight: 700;
    color: var(--color-neutral-900);
    white-space: nowrap;
}

.photos__link:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: 2px;
    border-radius: 2px;
}

.photos__link-icon {
    width: 3.3cqw;
    height: 3.3cqw;
}

.photos__deck {
    flex: 1;
    position: relative;
    touch-action: none;
}

.photos__deck:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: -2px;
}

.photos__card {
    position: absolute;
    left: 5.75cqw;
    right: 5.75cqw;
    top: 0.9cqw;
    bottom: -12.8cqw;
    overflow: hidden;
    border: 1.1cqw solid #fff;
    border-radius: 4cqw;
    box-shadow: 0 2.2cqw 5.7cqw rgba(20, 22, 30, 0.22);
    cursor: grab;
    will-change: transform, opacity;
    backface-visibility: hidden;
}

.photos__card--top:active {
    cursor: grabbing;
}

.photos__card:not(.photos__card--top) {
    pointer-events: none;
}

.photos__card img {
    display: block;
    width: 100%;
    height: 100%;
    object-fit: cover;
    pointer-events: none;
    user-select: none;
    -webkit-user-drag: none;
}

.photos__dots {
    position: absolute;
    left: 0;
    right: 0;
    bottom: 3.1cqw;
    z-index: 6;
    display: flex;
    justify-content: center;
    gap: 1.3cqw;
    pointer-events: none;
}

.photos__dot {
    width: 1.3cqw;
    height: 1.3cqw;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.55);
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.35);
    transition: width 0.3s, background 0.3s, border-radius 0.3s;
}

.photos__dot--active {
    width: 3.5cqw;
    border-radius: 0.9cqw;
    background: #fff;
}
</style>
