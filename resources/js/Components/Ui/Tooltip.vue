<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';

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

function show() {
    updatePosition();
    visible.value = true;
}

function hide() {
    visible.value = false;
}

// Keep the bubble glued to its trigger while visible, since a teleported
// `position: fixed` element doesn't move with an ancestor's scroll (e.g. the
// Heatmap's mobile scroll container) the way an absolutely-positioned
// descendant would have.
function reposition() {
    if (visible.value) updatePosition();
}

window.addEventListener('scroll', reposition, true);
window.addEventListener('resize', reposition);

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

        <Teleport to="body">
            <span
                role="tooltip"
                aria-hidden="true"
                class="pointer-events-none fixed z-50 -translate-x-1/2 whitespace-nowrap rounded-md bg-neutral-900 px-3 py-1.5 text-xs font-medium text-white opacity-0 shadow-card transition-opacity duration-150"
                :class="[isTop ? '-translate-y-full' : '', visible ? 'opacity-100' : '']"
                :style="{ top: `${position.top}px`, left: `${position.left}px` }"
            >
                {{ label }}
                <span class="absolute left-1/2 size-2 -translate-x-1/2 rotate-45 bg-neutral-900" :class="isTop ? '-bottom-1' : '-top-1'" />
            </span>
        </Teleport>
    </span>
</template>
