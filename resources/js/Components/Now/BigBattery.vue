<script setup>
import { computed } from 'vue';

const props = defineProps({
    level: { type: Number, default: 0.69 },
    charging: { type: Boolean, default: false },
});

const clamped = computed(() => Math.max(0, Math.min(1, props.level)));
const percent = computed(() => Math.round(clamped.value * 100));
const fillColor = computed(() => (clamped.value <= 0.2 ? '#ff3b30' : '#34c759'));
</script>

<template>
    <div class="battery" :class="{ charging }" aria-hidden="true">
        <div class="body">
            <div class="fill" :style="{ width: `${percent}%`, background: fillColor }">
                <span class="sheen" />
            </div>
            <svg v-if="charging" class="bolt" viewBox="0 0 24 24" fill="#fff" stroke="rgba(0,0,0,0.18)" stroke-width="0.6">
                <path d="M6.19 11.4 12.19 3.31c.47-.63 1.35-.24 1.35.6v6.26c0 .5.35.91.77.91h2.92c.66 0 1.02.93.58 1.52l-6 8.08c-.47.63-1.35.24-1.35-.6v-6.26c0-.5-.34-.91-.77-.91H6.77c-.66 0-1.02-.93-.58-1.52Z" />
            </svg>
        </div>
        <div class="cap" />
    </div>
</template>

<style scoped>
.battery {
    display: flex;
    align-items: center;
    width: 100%;
    max-width: 16rem;
}

.body {
    position: relative;
    flex: 1;
    height: 4.5rem;
    padding: 0.4rem;
    border: 3px solid currentColor;
    border-radius: 1rem;
    overflow: hidden;
}

.cap {
    width: 0.5rem;
    height: 1.8rem;
    margin-left: 0.25rem;
    border-radius: 0 0.3rem 0.3rem 0;
    background: currentColor;
}

.fill {
    position: relative;
    height: 100%;
    min-width: 0.9rem;
    border-radius: 0.6rem;
    overflow: hidden;
    transition: width 0.6s ease;
}

.sheen {
    position: absolute;
    inset: 0;
    transform: translateX(-100%);
    background: linear-gradient(100deg, transparent 20%, rgba(255, 255, 255, 0.55) 50%, transparent 80%);
}

.charging .sheen {
    animation: sheen 1.6s ease-in-out infinite;
}

.bolt {
    position: absolute;
    top: 50%;
    left: 50%;
    width: 2.4rem;
    height: 2.4rem;
    transform: translate(-50%, -50%);
    filter: drop-shadow(0 1px 2px rgba(0, 0, 0, 0.25));
    animation: bolt-pulse 1.6s ease-in-out infinite;
}

@keyframes sheen {
    0% {
        transform: translateX(-120%);
    }

    60%,
    100% {
        transform: translateX(120%);
    }
}

@keyframes bolt-pulse {
    0%,
    100% {
        transform: translate(-50%, -50%) scale(1);
        opacity: 0.92;
    }

    50% {
        transform: translate(-50%, -50%) scale(1.12);
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .charging .sheen,
    .bolt {
        animation: none;
    }
}
</style>
