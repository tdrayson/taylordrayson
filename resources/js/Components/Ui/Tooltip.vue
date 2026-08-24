<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { tooltipSuppressed } from '../../lib/tooltip.js';
import { useMounted } from '../../composables/useMounted';

const mounted = useMounted();

const props = defineProps({
    label: { type: String, required: true },
    placement: { type: String, default: 'bottom' },
});

const isTop = computed(() => props.placement === 'top');

// The trigger element (this component's root span), measured on show to
// compute the teleported bubble's fixed position.
const triggerRef = ref(null);
// Whether the bubble is currently shown; drives the opacity transition since
// the bubble is no longer a CSS-hoverable descendant of the trigger once
// teleported to <body>.
const visible = ref(false);
// Fixed-position coordinates (viewport-relative) for the teleported bubble.
const position = ref({ top: 0, left: 0 });

const GAP = 8; // matches the previous mb-2/mt-2 (0.5rem) offset from the trigger

/**
 * Measure the trigger's bounding rect and derive the fixed top/left for the
 * bubble: horizontally centred on the trigger, and offset above or below it
 * depending on placement (mirrors the old bottom-full/top-full + margin CSS).
 */
function updatePosition() {
    if (!triggerRef.value) return;

    const rect = triggerRef.value.getBoundingClientRect();

    position.value = {
        left: rect.left + rect.width / 2,
        top: isTop.value ? rect.top - GAP : rect.bottom + GAP,
    };
}

// Keep the bubble glued to its trigger while visible, since a teleported
// `position: fixed` element doesn't move with an ancestor's scroll (e.g. the
// Heatmap's mobile scroll container) the way an absolutely-positioned
// descendant would have.
function reposition() {
    updatePosition();
}

function canHover() {
    return typeof window === 'undefined' || window.matchMedia('(hover: hover)').matches;
}

function show() {
    // Per show: a tablet gains a pointer when a keyboard is attached.
    if (tooltipSuppressed(triggerRef.value, canHover())) {
        return;
    }

    updatePosition();
    visible.value = true;
    // Only listen while the bubble is actually shown, so idle tooltips (e.g.
    // ~366 in the year heatmap) don't each register global listeners.
    window.addEventListener('scroll', reposition, true);
    window.addEventListener('resize', reposition);
}

function hide() {
    visible.value = false;
    window.removeEventListener('scroll', reposition, true);
    window.removeEventListener('resize', reposition);
}

// Guard against unmounting while still visible (e.g. the trigger's parent
// list re-renders mid-hover) so the listeners don't leak.
onBeforeUnmount(() => {
    window.removeEventListener('scroll', reposition, true);
    window.removeEventListener('resize', reposition);
});
</script>

<template>
    <span
        ref="triggerRef"
        class="group relative inline-flex"
        @mouseenter="show"
        @mouseleave="hide"
        @focusin="show"
        @focusout="hide"
    >
        <slot />

        <Teleport v-if="mounted" to="body">
            <Transition name="tooltip-fade">
                <span
                    v-if="visible"
                    role="tooltip"
                    aria-hidden="true"
                    class="pointer-events-none fixed z-50 -translate-x-1/2 whitespace-nowrap rounded-md bg-black px-3 py-1.5 text-xs font-medium text-white shadow-card"
                    :class="isTop ? '-translate-y-full' : ''"
                    :style="{ top: `${position.top}px`, left: `${position.left}px` }"
                >
                    {{ label }}
                    <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
                    <span class="absolute left-1/2 size-2 -translate-x-1/2 rotate-45 bg-black" :class="isTop ? '-bottom-1' : '-top-1'" />
                </span>
            </Transition>
        </Teleport>
    </span>
</template>

<style scoped>
/* Mirrors the previous always-mounted opacity-0/opacity-100 + transition-opacity
   duration-150 classes, now driven by v-if via Transition since the bubble only
   exists in the DOM while visible. */
.tooltip-fade-enter-active,
.tooltip-fade-leave-active {
    transition: opacity 150ms;
}

.tooltip-fade-enter-from,
.tooltip-fade-leave-to {
    opacity: 0;
}
</style>
