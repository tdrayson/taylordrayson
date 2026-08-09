<script setup>
import { computed } from 'vue';

const props = defineProps({
    move: { type: Number, default: 62 },
    exercise: { type: Number, default: 25 },
    stand: { type: Number, default: 75 },
    mono: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
    large: { type: Boolean, default: false },
    huge: { type: Boolean, default: false },
    animate: { type: Boolean, default: false },
});

const colors = computed(() =>
    props.mono ? ['currentColor', 'currentColor', 'currentColor'] : ['#fa114f', '#a4e000', '#00c2d4']
);
</script>

<template>
    <svg viewBox="0 0 37 37" :class="[huge ? 'size-44' : large ? 'size-28' : compact ? 'size-4' : 'size-6', { animate }]" aria-hidden="true">
        <g class="ring ring1">
            <circle class="ring-bg" :stroke="colors[0]" stroke-width="3" r="15.915" cx="50%" cy="50%" />
            <circle class="ring-fg" :stroke="colors[0]" stroke-width="3" r="15.915" cx="50%" cy="50%" :stroke-dasharray="`${move}, 100`" :style="{ '--len': move }" />
        </g>
        <g class="ring ring2">
            <circle class="ring-bg" :stroke="colors[1]" stroke-width="4" r="15.915" cx="50%" cy="50%" />
            <circle class="ring-fg" :stroke="colors[1]" stroke-width="4" r="15.915" cx="50%" cy="50%" :stroke-dasharray="`${exercise}, 100`" :style="{ '--len': exercise }" />
        </g>
        <g class="ring ring3">
            <circle class="ring-bg" :stroke="colors[2]" stroke-width="6" r="15.915" cx="50%" cy="50%" />
            <circle class="ring-fg" :stroke="colors[2]" stroke-width="6" r="15.915" cx="50%" cy="50%" :stroke-dasharray="`${stand}, 100`" :style="{ '--len': stand }" />
        </g>
    </svg>
</template>

<style scoped>
.ring {
    transform-origin: center;
    transform-box: view-box;
}

.ring1 {
    transform: scale(1) rotate(-90deg);
}

.ring2 {
    transform: scale(0.75) rotate(-90deg);
}

.ring3 {
    transform: scale(0.5) rotate(-90deg);
}

.ring-bg {
    fill: none;
    stroke-opacity: 0.18;
}

.ring-fg {
    fill: none;
    stroke-linecap: round;
}

/* Draw each ring on from empty, staggered, when `animate` is set. */
.animate .ring-fg {
    animation: ring-fill 1s cubic-bezier(0.33, 1, 0.68, 1) both;
}

.animate .ring1 .ring-fg {
    animation-delay: 0.05s;
}

.animate .ring2 .ring-fg {
    animation-delay: 0.18s;
}

.animate .ring3 .ring-fg {
    animation-delay: 0.31s;
}

@keyframes ring-fill {
    from {
        stroke-dashoffset: var(--len, 0);
    }

    to {
        stroke-dashoffset: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .animate .ring-fg {
        animation: none;
    }
}
</style>
