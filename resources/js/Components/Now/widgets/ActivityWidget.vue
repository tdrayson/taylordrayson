<script setup>
import { reactive, computed, onMounted, useId } from 'vue';

const props = defineProps({
    variant: { type: String, default: 'light' },
    // When true, the card fills its grid cell height (to match square tiles)
    // instead of imposing its own 2:1 ratio.
    fill: { type: Boolean, default: false },
    move: { type: Number, default: 137 },
    moveGoal: { type: Number, default: 190 },
    exercise: { type: Number, default: 24 },
    exerciseGoal: { type: Number, default: 30 },
    stand: { type: Number, default: 9 },
    standGoal: { type: Number, default: 12 },
});

const uid = useId();
const gradId = (key) => `${uid}-${key}`;

const TAU = 2 * Math.PI;

const rings = computed(() =>
    [
        { key: 'move', r: 42, label: 'Move', unit: 'CAL', value: props.move, goal: props.moveGoal, base: '#FB1B53' },
        { key: 'exercise', r: 30, label: 'Exercise', unit: 'MIN', value: props.exercise, goal: props.exerciseGoal, base: '#84DB20' },
        { key: 'stand', r: 18, label: 'Stand', unit: 'HRS', value: props.stand, goal: props.standGoal, base: '#15D9D9' },
    ].map((ring) => {
        const circ = TAU * ring.r;
        const fraction = Math.max(0, Math.min(1, ring.goal ? ring.value / ring.goal : 0));

        return { ...ring, circ, offset: circ * (1 - fraction) };
    }),
);

const labelColor = (ring) => {
    if (ring.key === 'exercise') {
        return props.variant === 'light' ? '#5FB80A' : '#84DB20';
    }

    return ring.base;
};

// Rings start empty and counters at zero; both animate to target on mount.
const offsets = reactive({});
const counts = reactive({});
rings.value.forEach((ring) => {
    offsets[ring.key] = ring.circ;
    counts[ring.key] = 0;
});

onMounted(() => {
    const reduce = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

    if (reduce) {
        rings.value.forEach((ring) => {
            offsets[ring.key] = ring.offset;
            counts[ring.key] = ring.value;
        });
        return;
    }

    // Draw the rings on (paint the empty state first, then transition).
    requestAnimationFrame(() =>
        requestAnimationFrame(() => {
            rings.value.forEach((ring) => {
                offsets[ring.key] = ring.offset;
            });
        }),
    );

    // Count the metrics up with an ease-out.
    const start = performance.now();
    const duration = 1100;
    const tick = (now) => {
        const p = Math.min((now - start) / duration, 1);
        const eased = 1 - Math.pow(1 - p, 3);
        rings.value.forEach((ring) => {
            counts[ring.key] = Math.round(ring.value * eased);
        });
        if (p < 1) {
            requestAnimationFrame(tick);
        }
    };
    requestAnimationFrame(tick);
});
</script>

<template>
    <div class="activity rounded-3xl" :class="[`activity--${variant}`, { 'activity--has-aspect': !fill }]">
        <div class="activity__inner">
            <div class="activity__rings">
                <svg viewBox="0 0 100 100">
                    <defs>
                        <linearGradient :id="gradId('move')" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#FF4D72" />
                            <stop offset="1" stop-color="#FA114F" />
                        </linearGradient>
                        <linearGradient :id="gradId('exercise')" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#C6FF4D" />
                            <stop offset="1" stop-color="#84DB20" />
                        </linearGradient>
                        <linearGradient :id="gradId('stand')" x1="0" y1="0" x2="1" y2="1">
                            <stop offset="0" stop-color="#5BF5F5" />
                            <stop offset="1" stop-color="#15D9D9" />
                        </linearGradient>
                    </defs>

                    <circle v-for="ring in rings" :key="`track-${ring.key}`" class="activity__ring-track" cx="50" cy="50" :r="ring.r" :stroke="ring.base" />
                    <circle
                        v-for="(ring, i) in rings"
                        :key="`fill-${ring.key}`"
                        class="activity__ring-fill"
                        cx="50"
                        cy="50"
                        :r="ring.r"
                        :stroke="`url(#${gradId(ring.key)})`"
                        :stroke-dasharray="ring.circ"
                        :style="{ strokeDashoffset: offsets[ring.key], transitionDelay: `${0.05 + i * 0.12}s` }"
                    />
                </svg>
            </div>

            <div class="activity__metrics">
                <div v-for="ring in rings" :key="ring.key" class="activity__metric">
                    <div class="activity__metric-label" :style="{ color: labelColor(ring) }">{{ ring.label }}</div>
                    <div class="activity__metric-value">{{ counts[ring.key] }}<small class="activity__metric-unit">/{{ ring.goal }} {{ ring.unit }}</small></div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 4.4) so the whole 2:1 widget scales with the grid cell. */
.activity {
    container-type: inline-size;
    box-shadow: var(--shadow-card);
}

/* Standalone (not in a square-defined grid row) keeps the iOS 2:1 ratio. */
.activity--has-aspect {
    aspect-ratio: 2 / 1;
}

.activity--dark {
    background: linear-gradient(165deg, #232327, #0e0e10);
    color: #fff;
}

.activity--light {
    background: var(--color-neutral-0);
    color: var(--color-neutral-900);
}

/* Padding/flex live on a descendant so their cqw values reference the card
   (a container can't query itself). */
.activity__inner {
    display: flex;
    height: 100%;
    align-items: center;
    gap: 3.2cqw;
    padding: 3cqw 5cqw;
}

.activity__rings {
    width: 42cqw;
    height: 42cqw;
    flex: none;
    filter: drop-shadow(0 0.9cqw 2.3cqw rgba(0, 0, 0, 0.25));
}

.activity__rings svg {
    display: block;
    width: 100%;
    height: 100%;
}

.activity__ring-track {
    fill: none;
    stroke-width: 9;
}

.activity--dark .activity__ring-track {
    opacity: 0.2;
}

.activity--light .activity__ring-track {
    opacity: 0.16;
}

.activity__ring-fill {
    fill: none;
    stroke-width: 9;
    stroke-linecap: round;
    transform: rotate(-90deg);
    transform-origin: 50px 50px;
    transition: stroke-dashoffset 1.2s cubic-bezier(0.32, 1, 0.38, 1);
}

.activity__metrics {
    display: flex;
    flex: 1;
    flex-direction: column;
    justify-content: center;
    gap: 3cqw;
    padding-left: 1.4cqw;
}

.activity__metric-label {
    font-size: 3cqw;
    font-weight: 800;
    letter-spacing: 0.01em;
}

.activity__metric-value {
    margin-top: 0.5cqw;
    font-size: 6.1cqw;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
    font-variant-numeric: tabular-nums;
}

.activity__metric-unit {
    font-size: 3cqw;
    font-weight: 600;
    letter-spacing: 0;
}

.activity--dark .activity__metric-value {
    color: #fff;
}

.activity--dark .activity__metric-unit {
    color: rgba(255, 255, 255, 0.5);
}

.activity--light .activity__metric-value {
    color: #16181c;
}

.activity--light .activity__metric-unit {
    color: #9aa0a8;
}

@media (prefers-reduced-motion: reduce) {
    .activity__ring-fill {
        transition: none;
    }
}
</style>
