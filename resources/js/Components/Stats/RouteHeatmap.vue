<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import { CenterFocusIcon } from '@hugeicons-pro/core-stroke-rounded';
import { decodePolyline } from '../../lib/geo.js';

const props = defineProps({
    // Encoded polyline strings; each is drawn as one faint line, so overlapping
    // routes stack into a density heatmap of where you move most.
    polylines: { type: Array, default: () => [] },
    color: { type: String, default: '#2ea06b' },
    heightClass: { type: String, default: 'h-full min-h-80' },
});

const MAPLIBRE_VERSION = '4.7.1';
const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';
const FIT_OPTIONS = { padding: 32, maxZoom: 14 };

const container = ref(null);
const ready = ref(false);
let map = null;
let savedBounds = null;
let resizeObserver = null;

function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
}

function loadStylesheet(href) {
    if (document.querySelector(`link[href="${href}"]`)) {
        return;
    }

    const link = document.createElement('link');
    link.rel = 'stylesheet';
    link.href = href;
    document.head.appendChild(link);
}

function loadScript(src) {
    return new Promise((resolve, reject) => {
        const existing = document.querySelector(`script[src="${src}"]`);

        if (existing) {
            window.maplibregl ? resolve() : (existing.addEventListener('load', resolve), existing.addEventListener('error', reject));

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

onMounted(async () => {
    loadStylesheet(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`);

    try {
        await loadScript(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.js`);
    } catch (error) {
        console.error('[RouteHeatmap] failed to load MapLibre', error);

        return;
    }

    if (!window.maplibregl) {
        return;
    }

    // Decode every route to line coordinates, dropping any that fail to parse.
    const lines = props.polylines.map((encoded) => decodePolyline(encoded)).filter((coords) => coords.length > 1);

    if (lines.length === 0) {
        return;
    }

    const { maplibregl } = window;

    // Bounds span every route so the fit shows the whole footprint.
    const bounds = lines.flat().reduce(
        (box, coordinate) => box.extend(coordinate),
        new maplibregl.LngLatBounds(lines[0][0], lines[0][0]),
    );

    map = new maplibregl.Map({
        container: container.value,
        style: STYLE_URL,
        bounds,
        fitBoundsOptions: FIT_OPTIONS,
        attributionControl: false,
    });

    savedBounds = bounds;
    ready.value = true;
    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    // The card sizes via flex, so the canvas can mount before its box settles;
    // observe the container and resize the map to fill it.
    resizeObserver = new ResizeObserver(() => map?.resize());
    resizeObserver.observe(container.value);

    map.on('load', () => {
        map.resize();

        map.addSource('routes', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: lines.map((coords) => ({ type: 'Feature', geometry: { type: 'LineString', coordinates: coords } })),
            },
        });

        // Solid routes in the type colour, like a normal route map, so paths read
        // clearly against the basemap rather than washing out as a faint heatmap.
        map.addLayer({
            id: 'routes',
            type: 'line',
            source: 'routes',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': props.color, 'line-width': 2.5, 'line-opacity': 0.9 },
        });
    });
});

onBeforeUnmount(() => {
    resizeObserver?.disconnect();
    map?.remove();
});
</script>

<template>
    <div class="relative size-full">
        <div ref="container" class="w-full overflow-hidden rounded-lg border border-neutral-50" :class="heightClass" />
        <button
            v-if="ready"
            type="button"
            class="absolute left-2.5 top-2.5 z-10 flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-white text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
            aria-label="Re-center map"
            @click="recenter"
        >
            <Icon :icon="CenterFocusIcon" class="size-4" />
        </button>
    </div>
</template>
