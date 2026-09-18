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
    <div class="group @container relative aspect-square overflow-hidden rounded-3xl bg-neutral-100 shadow-card">
        <div ref="mapContainer" class="location-map absolute inset-0 z-0 overflow-hidden rounded-3xl" />
        <div class="location-scrim pointer-events-none absolute inset-0 z-2" />

        <div class="absolute top-47/100 left-1/2 z-4 size-3/14 -translate-1/2">
            <span class="pointer-events-none absolute inset-0 animate-location-pulse rounded-full bg-accent-500 opacity-0 group-hover:animate-location-pulse-hover motion-reduce:hidden" />
            <span class="pointer-events-none absolute inset-0 animate-location-pulse-late rounded-full bg-accent-500 opacity-0 group-hover:animate-location-pulse-hover-late motion-reduce:hidden" />
            <div class="relative size-full overflow-hidden rounded-full border-2 border-white bg-accent-100 shadow-lg shadow-black/30 @4xs:border-3 @xs:border-4 @xs:shadow-xl">
                <img class="size-full object-cover object-top" :src="avatar" :alt="name" />
            </div>
        </div>

        <div class="absolute bottom-7/100 left-1/13 z-5">
            <div class="text-lg leading-none font-extrabold tracking-tight text-neutral-900 text-shadow-lg text-shadow-neutral-0/70 @5xs:text-xl @4xs:text-2xl @xs:text-4xl">
                {{ name }}
            </div>
            <div class="mt-1 text-2xs font-semibold text-neutral-500 text-shadow-lg text-shadow-neutral-0/70 @5xs:text-xs @4xs:mt-1.5 @4xs:text-sm @xs:mt-2 @xs:text-lg">
                {{ city }}
            </div>
        </div>
    </div>
</template>

<style scoped>
/* A WebGL canvas composites on its own layer, which an ancestor's overflow does not always clip. */
.location-map :deep(.maplibregl-canvas) {
    outline: none;
    border-radius: inherit;
}

.location-map :deep(.maplibregl-canvas-container) {
    border-radius: inherit;
}

.location-scrim {
    background: radial-gradient(130% 90% at 0% 100%, color-mix(in srgb, var(--color-neutral-0) 92%, transparent), transparent 52%);
}
</style>
