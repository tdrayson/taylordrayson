<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { loadMaplibre, mapStyleForTheme } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    name: { type: String, default: 'Taylor' },
    city: { type: String, default: 'London, UK' },
    avatar: { type: String, default: '/taylor-cutout.png' },
    // Coarsened, privacy-safe coordinates sent to the page. The real location
    // stays server-side; only this rounded pair and a zoomed-out map are shown.
    latitude: { type: Number, default: 51 },
    longitude: { type: Number, default: 0 },
});

const mapContainer = ref(null);
let map = null;
let stopThemeWatch = null;

// Match the basemap to the active colour scheme, like the shared map components.
const { resolved } = useTheme();

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    // Guard against the widget unmounting before the chunk resolves.
    if (!maplibregl || !mapContainer.value) {
        return;
    }

    map = new maplibregl.Map({
        container: mapContainer.value,
        style: mapStyleForTheme(resolved.value),
        center: [props.longitude, props.latitude],
        zoom: 5.6,
        interactive: false,
        attributionControl: false,
    });

    // Swap the basemap when the scheme flips. Nothing to re-add after setStyle:
    // the marker, pulse and label are DOM overlays, not map sources/layers.
    stopThemeWatch = watch(resolved, (value) => {
        map?.setStyle(mapStyleForTheme(value));
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();

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

/* Rounded here as well as on the card: a WebGL canvas composites on its own
   layer, which an ancestor's overflow does not always clip. */
.location__map {
    position: absolute;
    inset: 0;
    z-index: 0;
    overflow: hidden;
    border-radius: inherit;
}

.location__map :deep(.maplibregl-canvas) {
    outline: none;
    border-radius: inherit;
}

.location__map :deep(.maplibregl-canvas-container) {
    border-radius: inherit;
}

/* Soft surface-coloured wash rising from the bottom-left so the label stays
   readable over either basemap: white in light, near-black in dark. */
.location__scrim {
    position: absolute;
    inset: 0;
    z-index: 2;
    pointer-events: none;
    background: radial-gradient(130% 90% at 0% 100%, color-mix(in srgb, var(--color-neutral-0) 92%, transparent), transparent 52%);
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
    background: var(--color-accent-100);
}

.location__avatar-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    object-position: top;
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
    color: var(--color-neutral-900);
    text-shadow: 0 0.43cqw 4.31cqw color-mix(in srgb, var(--color-neutral-0) 70%, transparent);
}

.location__city {
    margin-top: 2.16cqw;
    font-size: 5.6cqw;
    font-weight: 600;
    color: var(--color-neutral-500);
    text-shadow: 0 0.43cqw 3.45cqw color-mix(in srgb, var(--color-neutral-0) 70%, transparent);
}
</style>
