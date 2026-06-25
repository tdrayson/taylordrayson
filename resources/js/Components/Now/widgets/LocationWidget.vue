<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import 'maplibre-gl/dist/maplibre-gl.css';

const props = defineProps({
    name: { type: String, default: 'Taylor' },
    city: { type: String, default: 'London, UK' },
    avatar: { type: String, default: '/headshot-taylor.jpg' },
    // Coarsened, privacy-safe coordinates sent to the page. The real location
    // stays server-side; only this rounded pair and a zoomed-out map are shown.
    latitude: { type: Number, default: 51 },
    longitude: { type: Number, default: 0 },
});

const mapContainer = ref(null);
let map = null;

onMounted(async () => {
    const { Map } = await import('maplibre-gl');

    // Guard against the widget unmounting before the chunk resolves.
    if (!mapContainer.value) {
        return;
    }

    map = new Map({
        container: mapContainer.value,
        style: 'https://tiles.openfreemap.org/styles/positron',
        center: [props.longitude, props.latitude],
        zoom: 5.6,
        interactive: false,
        attributionControl: false,
    });
});

onBeforeUnmount(() => {
    if (map) {
        map.remove();
        map = null;
    }
});
</script>

<template>
    <div class="location rounded-3xl">
        <div ref="mapContainer" class="location__map" />
        <div class="location__scrim" />

        <div class="location__marker">
            <span class="location__pulse" />
            <span class="location__pulse location__pulse--delayed" />
            <div class="location__avatar">
                <img class="location__avatar-image" :src="avatar" :alt="name" />
            </div>
        </div>

        <div class="location__label">
            <div class="location__name">{{ name }}</div>
            <div class="location__city">{{ city }}</div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; overlay sizing is in cqw (1cqw ≈ reference
   px ÷ 2.32). The map fills the card; marker and label are positioned over it. */
.location {
    container-type: inline-size;
    position: relative;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    background: var(--color-neutral-100);
    box-shadow: var(--shadow-card);
}

.location__map {
    position: absolute;
    inset: 0;
    z-index: 0;
}

.location__map :deep(.maplibregl-canvas) {
    outline: none;
}

/* Soft white wash rising from the bottom-left so the label stays readable. */
.location__scrim {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: radial-gradient(130% 90% at 0% 100%, rgba(255, 255, 255, 0.92), rgba(255, 255, 255, 0) 52%);
}

.location__marker {
    position: absolute;
    left: 50%;
    top: 47%;
    transform: translate(-50%, -50%);
    z-index: 4;
}

/* Expanding accent rings rippling out from behind the avatar. */
.location__pulse {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 21.55cqw;
    height: 21.55cqw;
    border-radius: 50%;
    background: var(--color-accent-500);
    transform: translate(-50%, -50%) scale(0.7);
    opacity: 0;
    pointer-events: none;
    animation: location-pulse 10s ease-out infinite;
}

.location__pulse--delayed {
    animation-delay: 0.7s;
}

@keyframes location-pulse {
    0% {
        transform: translate(-50%, -50%) scale(0.7);
        opacity: 0.5;
    }

    14% {
        opacity: 0;
    }

    20%,
    100% {
        transform: translate(-50%, -50%) scale(2.6);
        opacity: 0;
    }
}

/* On hover, swap to a continuous ripple (a fresh animation name restarts it
   immediately) so the location pings right away under the cursor. */
.location:hover .location__pulse {
    animation-name: location-pulse-hover;
    animation-duration: 2.6s;
}

@keyframes location-pulse-hover {
    0% {
        transform: translate(-50%, -50%) scale(0.7);
        opacity: 0.5;
    }

    70% {
        opacity: 0;
    }

    100% {
        transform: translate(-50%, -50%) scale(2.6);
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .location__pulse {
        animation: none;
        display: none;
    }
}

.location__avatar {
    position: relative;
    width: 21.55cqw;
    height: 21.55cqw;
    border-radius: 50%;
    overflow: hidden;
    border: 1.29cqw solid #fff;
    box-shadow: 0 3cqw 7.76cqw rgba(20, 22, 30, 0.32);
    background: var(--color-accent-500);
}

.location__avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.location__label {
    position: absolute;
    left: 7.76cqw;
    bottom: 6.9cqw;
    z-index: 5;
}

.location__name {
    font-size: 10.78cqw;
    font-weight: 800;
    letter-spacing: -0.025em;
    line-height: 1;
    color: #1f2733;
    text-shadow: 0 0.43cqw 4.31cqw rgba(255, 255, 255, 0.7);
}

.location__city {
    margin-top: 2.16cqw;
    font-size: 5.6cqw;
    font-weight: 600;
    color: #7c8593;
    text-shadow: 0 0.43cqw 3.45cqw rgba(255, 255, 255, 0.7);
}
</style>
