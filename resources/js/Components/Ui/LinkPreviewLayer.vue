<script setup>
import { nextTick, ref, watch, onBeforeUnmount } from 'vue';
import LinkPreviewCard from './LinkPreviewCard.vue';

const props = defineProps({
    // Map of href -> preview data (from server page props).
    previews: { type: Object, default: () => ({}) },
    // The content element whose internal <a> links get previews.
    container: { type: [Object, null], default: null },
});

const active = ref(null);
const popEl = ref(null);
const pos = ref({ top: 0, left: 0, placement: 'top' });
const CARD_W = 320;
const URL_W = 360;
const GAP = 8;
const OPEN_DELAY = 350;
const CLOSE_DELAY = 150;

let openTimer = null;
let closeTimer = null;

// Touch devices have no hover; skip previews there entirely.
const canHover = typeof window !== 'undefined' && window.matchMedia('(hover: hover)').matches;

// Place the card centered over the link, clamped to the viewport, flipping
// below the link when there isn't room above.
function placeFor(el, width, minRoomAbove) {
    const rect = el.getBoundingClientRect();
    let left = rect.left + rect.width / 2 - width / 2;
    left = Math.max(GAP, Math.min(left, window.innerWidth - width - GAP));
    const placement = rect.top > minRoomAbove ? 'top' : 'bottom';
    const top = placement === 'top' ? rect.top - GAP : rect.bottom + GAP;
    return { top, left, placement };
}

function open(el, preview, immediate = false) {
    clearTimeout(closeTimer);
    const run = async () => {
        const isUrl = preview.kind === 'url';
        pos.value = placeFor(el, isUrl ? URL_W : CARD_W, isUrl ? 80 : 280);
        active.value = preview;

        if (isUrl) {
            await nextTick();
            pos.value = placeFor(el, popEl.value?.getBoundingClientRect().width ?? URL_W, 80);
        }
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
function previewFor(link) {
    const preview = props.previews[link.getAttribute('href')];

    if (preview) {
        return { kind: 'card', preview };
    }

    // No entry behind it, so it points off-site. The href is the whole payload:
    // the chip shows a shortened label, this shows exactly where it lands.
    return link.dataset.external !== undefined
        ? { kind: 'url', url: link.getAttribute('href') }
        : null;
}

function handleMouseOver(event) {
    const link = event.target.closest('a[href]');
    if (!link || link === openLink) {
        return;
    }
    const preview = previewFor(link);
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
    const preview = previewFor(link);
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

// Attach delegated listeners to the given element, not to individual <a>
// nodes, so the binding survives Inertia patching the container's inner
// content.
function bind(el) {
    if (!canHover || !el) {
        return;
    }
    el.addEventListener('mouseover', handleMouseOver);
    el.addEventListener('mouseout', handleMouseOut);
    el.addEventListener('focusin', handleFocusIn);
    el.addEventListener('focusout', handleFocusOut);
}

function unbind(el) {
    if (!canHover || !el) {
        return;
    }
    el.removeEventListener('mouseover', handleMouseOver);
    el.removeEventListener('mouseout', handleMouseOut);
    el.removeEventListener('focusin', handleFocusIn);
    el.removeEventListener('focusout', handleFocusOut);
}

// Watched rather than bound in onMounted: the parent's template ref is still
// null at that point, so binding once on mount would miss it entirely.
watch(
    () => props.container,
    (el, prev) => {
        if (prev) {
            unbind(prev);
        }
        if (el) {
            bind(el);
        }
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    clearTimeout(openTimer);
    clearTimeout(closeTimer);
    unbind(props.container);
});
</script>

<template>
    <Teleport to="body">
        <Transition name="fade">
            <div
                v-if="active"
                ref="popEl"
                class="fixed z-50 motion-reduce:transition-none"
                :class="active.kind === 'url' ? 'w-fit max-w-90' : 'w-80'"
                :style="{
                    top: `${pos.top}px`,
                    left: `${pos.left}px`,
                    transform: pos.placement === 'top' ? 'translateY(-100%)' : 'none',
                }"
                @mouseenter="keepOpen"
                @mouseleave="scheduleClose"
            >
                <LinkPreviewCard v-if="active.kind === 'card'" :preview="active.preview" />

                <p
                    v-else
                    class="url-preview rounded-lg border border-neutral-100 bg-neutral-0 px-2.5 py-1.5 font-mono text-caption text-neutral-500 shadow-card"
                >{{ active.url }}</p>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
/* A URL has no spaces to break on, so it would otherwise push the popover
   past its own width. */
.url-preview {
    overflow-wrap: anywhere;
}

.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.12s ease;
}

.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
