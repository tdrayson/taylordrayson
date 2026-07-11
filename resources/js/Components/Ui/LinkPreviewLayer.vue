<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue';
import LinkPreviewCard from './LinkPreviewCard.vue';

const props = defineProps({
    // Map of href -> preview data (from server page props).
    previews: { type: Object, default: () => ({}) },
    // The content element whose internal <a> links get previews.
    container: { type: [Object, null], default: null },
});

const active = ref(null);
const pos = ref({ top: 0, left: 0, placement: 'top' });
const CARD_W = 320;
const GAP = 8;
const OPEN_DELAY = 350;
const CLOSE_DELAY = 150;

let openTimer = null;
let closeTimer = null;

// Touch devices have no hover; skip previews there entirely.
const canHover = typeof window !== 'undefined' && window.matchMedia('(hover: hover)').matches;

// Place the card centered over the link, clamped to the viewport, flipping
// below the link when there isn't room above.
function placeFor(el) {
    const rect = el.getBoundingClientRect();
    let left = rect.left + rect.width / 2 - CARD_W / 2;
    left = Math.max(GAP, Math.min(left, window.innerWidth - CARD_W - GAP));
    const placement = rect.top > 280 ? 'top' : 'bottom';
    const top = placement === 'top' ? rect.top - GAP : rect.bottom + GAP;
    return { top, left, placement };
}

function open(el, preview, immediate = false) {
    clearTimeout(closeTimer);
    const run = () => {
        pos.value = placeFor(el);
        active.value = preview;
    };
    if (immediate) {
        run();
    } else {
        openTimer = setTimeout(run, OPEN_DELAY);
    }
}

function scheduleClose() {
    clearTimeout(openTimer);
    closeTimer = setTimeout(() => {
        active.value = null;
    }, CLOSE_DELAY);
}

function keepOpen() {
    clearTimeout(closeTimer);
}

// Attach listeners to each internal <a> whose href has a preview.
function bind() {
    if (!canHover || !props.container) {
        return;
    }
    props.container.querySelectorAll('a[href]').forEach((link) => {
        const preview = props.previews[link.getAttribute('href')];
        if (!preview) {
            return;
        }
        link.addEventListener('mouseenter', () => open(link, preview));
        link.addEventListener('mouseleave', scheduleClose);
        link.addEventListener('focus', () => open(link, preview, true));
        link.addEventListener('blur', scheduleClose);
    });
}

onMounted(bind);
onBeforeUnmount(() => {
    clearTimeout(openTimer);
    clearTimeout(closeTimer);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="active"
                class="fixed z-50 w-80 motion-reduce:transition-none"
                :style="{
                    top: `${pos.top}px`,
                    left: `${pos.left}px`,
                    transform: pos.placement === 'top' ? 'translateY(-100%)' : 'none',
                }"
                @mouseenter="keepOpen"
                @mouseleave="scheduleClose"
            >
                <LinkPreviewCard :preview="active" />
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
