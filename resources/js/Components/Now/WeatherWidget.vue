<script setup>
import { computed } from 'vue';
import { weatherFor } from '../../lib/weather.js';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    condition: { type: String, default: 'partly-cloudy' },
    temp: { type: Number, default: null },
    // Humidity (%) and wind (mph) as the phone last reported them. Null until
    // a reading has been sent, in which case the pill simply does not render:
    // an invented number is worse than an absent one.
    humidity: { type: Number, default: null },
    wind: { type: Number, default: null },
});

// Icon, copy and gradient all come from the shared condition table, so the
// tile and the top bar can never disagree about the same sky.
const data = computed(() => weatherFor(props.condition));
const temp = computed(() => props.temp ?? data.value.t);
</script>

<template>
    <div class="weather relative aspect-square overflow-hidden rounded-3xl bg-neutral-0 shadow-card">
        <div class="weather__aura" :style="{ background: data.gradient }" />
        <div class="weather__inner">
            <p class="weather__headline">{{ data.line }}</p>
            <div class="weather__current">
                <Icon class="weather__icon" :icon="data.icon" :stroke-width="1.8" />
                <span v-if="temp !== null" class="weather__temp">{{ temp }}°</span>
            </div>
            <!-- Bare readings, with the tooltip carrying what each one is. The
                 labels spelled out wrapped each pill onto two lines in a tile
                 this small. -->
            <div v-if="humidity !== null || wind !== null" class="weather__range">
                <Tooltip v-if="humidity !== null" :label="`${humidity}% humidity`" placement="top">
                    <span class="weather__pill">{{ humidity }}%</span>
                </Tooltip>
                <Tooltip v-if="wind !== null" :label="`${wind} mph wind`" placement="top">
                    <span class="weather__pill">{{ wind }} mph</span>
                </Tooltip>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 2.48) so the whole composition scales with the grid cell. */
.weather {
    container-type: inline-size;
}

/* Soft corner aura: a blurred glow that bleeds from the bottom-right with no
   defined edge, rather than a crisp circle. */
.weather__aura {
    position: absolute;
    right: -20cqw;
    bottom: -20cqw;
    width: 86cqw;
    height: 86cqw;
    border-radius: 50%;
    z-index: 1;
    filter: blur(10cqw);
    opacity: 0.35;
    animation: weather-float 6.5s ease-in-out infinite;
}

.weather__inner {
    position: relative;
    z-index: 2;
    display: flex;
    height: 100%;
    flex-direction: column;
    padding: 10.5cqw;
}

.weather__headline {
    font-size: 10cqw;
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.02em;
    color: var(--color-neutral-900);
}

.weather__current {
    display: flex;
    align-items: center;
    gap: 5cqw;
    margin-top: 7cqw;
}

.weather__icon {
    width: 13.7cqw;
    height: 13.7cqw;
    flex: none;
    color: var(--color-neutral-500);
}

.weather__temp {
    font-size: 12cqw;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-neutral-900);
}

.weather__range {
    margin-top: auto;
    display: flex;
    gap: 3cqw;
}

.weather__pill {
    padding: 1.2cqw 4cqw;
    border-radius: 3.6cqw;
    font-size: 6.5cqw;
    font-weight: 600;
    color: #5e646c;
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(2.5cqw);
    -webkit-backdrop-filter: blur(2.5cqw);
}

@keyframes weather-float {
    0%,
    100% {
        transform: translate(0, 0);
    }

    50% {
        transform: translate(-2.8cqw, -2.8cqw);
    }
}

@media (prefers-reduced-motion: reduce) {
    .weather__aura {
        animation: none;
    }
}
</style>
