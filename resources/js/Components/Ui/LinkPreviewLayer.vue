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

// The <a> currently hovered/focused with an open (or opening) preview, so
// repeated bubbled events on the same link don't retrigger the open timer.
let openLink = null;

// Delegated handlers on the container: they resolve the target link and
// look up `props.previews` live on every event, so they stay correct across
// Inertia navigations that patch the container's content in place rather
// than remounting it (per-anchor listeners would otherwise go stale).
function handleMouseOver(event) {
    const link = event.target.closest('a[href]');
    if (!link || link === openLink) {
        return;
    }
    const preview = props.previews[link.getAttribute('href')];
    if (!preview) {
        return;
    }
    openLink = link;
    open(link, preview);
}

function handleMouseOut(event) {
    const link = event.target.closest('a[href]');
    if (!link || link !== openLink) {
        return;
    }
    // Ignore moves between the link's own children; only close when the
    // pointer actually leaves the link's subtree.
    if (event.relatedTarget && link.contains(event.relatedTarget)) {
        return;
    }
    openLink = null;
    scheduleClose();
}

function handleFocusIn(event) {
    const link = event.target.closest('a[href]');
    if (!link) {
        return;
    }
    const preview = props.previews[link.getAttribute('href')];
    if (!preview) {
        return;
    }
    openLink = link;
    open(link, preview, true);
}

function handleFocusOut(event) {
    const link = event.target.closest('a[href]');
    if (!link || link !== openLink) {
        return;
    }
    openLink = null;
    scheduleClose();
}

// Attach delegated listeners once to the container itself, not to
// individual <a> nodes, so the binding survives Inertia patching the
// container's inner content.
function bind() {
    if (!canHover || !props.container) {
        return;
    }
    props.container.addEventListener('mouseover', handleMouseOver);
    props.container.addEventListener('mouseout', handleMouseOut);
    props.container.addEventListener('focusin', handleFocusIn);
    props.container.addEventListener('focusout', handleFocusOut);
}

function unbind() {
    if (!canHover || !props.container) {
        return;
    }
    props.container.removeEventListener('mouseover', handleMouseOver);
    props.container.removeEventListener('mouseout', handleMouseOut);
    props.container.removeEventListener('focusin', handleFocusIn);
    props.container.removeEventListener('focusout', handleFocusOut);
}

onMounted(bind);
onBeforeUnmount(() => {
    clearTimeout(openTimer);
    clearTimeout(closeTimer);
    unbind();
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
