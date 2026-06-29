<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import Icon from '../Ui/Icon.vue';
import { CenterFocusIcon } from '@hugeicons-pro/core-stroke-rounded';

const props = defineProps({
    polyline: { type: String, required: true },
    color: { type: String, default: '#3858e9' },
});

const MAPLIBRE_VERSION = '4.7.1';
const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

// maxZoom caps how far fit-to-route zooms in, so short, tightly-clustered
// activities (e.g. padel) keep surrounding map context instead of filling the
// frame with an unreadable scribble.
const FIT_OPTIONS = { padding: 48, maxZoom: 17 };

const container = ref(null);
const ready = ref(false);
let map = null;
let savedBounds = null;

// Re-fit the view to the route's bounds after the visitor has panned or zoomed.
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
            if (window.maplibregl) {
                resolve();
            } else {
                existing.addEventListener('load', resolve);
                existing.addEventListener('error', reject);
            }

            return;
        }

        const script = document.createElement('script');
        script.src = src;
        script.onload = resolve;
        script.onerror = reject;
        document.head.appendChild(script);
    });
}

function resolveColor(value) {
    const match = /^var\((--[\w-]+)\)$/.exec(value);

    if (!match) {
        return value;
    }

    return getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() || '#3858e9';
}

function decodePolyline(value) {
    let index = 0;
    let lat = 0;
    let lng = 0;
    const coordinates = [];

    while (index < value.length) {
        let shift = 0;
        let result = 0;
        let byte;

        do {
            byte = value.charCodeAt(index++) - 63;
            result |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);
        lat += (result & 1) ? ~(result >> 1) : (result >> 1);

        shift = 0;
        result = 0;
        do {
            byte = value.charCodeAt(index++) - 63;
            result |= (byte & 0x1f) << shift;
            shift += 5;
        } while (byte >= 0x20);
        lng += (result & 1) ? ~(result >> 1) : (result >> 1);

        coordinates.push([lng / 1e5, lat / 1e5]);
    }

    return coordinates;
}

onMounted(async () => {
    loadStylesheet(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`);

    try {
        await loadScript(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.js`);
    } catch (error) {
        console.error('[EntryMap] failed to load MapLibre script', error);

        return;
    }

    const coords = decodePolyline(props.polyline);

    if (!window.maplibregl) {
        console.error('[EntryMap] window.maplibregl is undefined after script load');

        return;
    }

    if (coords.length === 0) {
        console.warn('[EntryMap] polyline decoded to 0 coordinates', props.polyline?.slice(0, 30));

        return;
    }

    const { maplibregl } = window;

    const bounds = coords.reduce(
        (box, coordinate) => box.extend(coordinate),
        new maplibregl.LngLatBounds(coords[0], coords[0]),
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

    map.on('error', (event) => console.error('[EntryMap] MapLibre error', event?.error || event));

    map.on('load', () => {
        map.addSource('route', {
            type: 'geojson',
            data: {
                type: 'Feature',
                geometry: { type: 'LineString', coordinates: coords },
            },
        });

        map.addLayer({
            id: 'route',
            type: 'line',
            source: 'route',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': resolveColor(props.color), 'line-width': 3.5 },
        });
    });
});

onBeforeUnmount(() => {
    map?.remove();
});
</script>

<template>
    <div class="relative">
        <div ref="container" class="h-72 w-full overflow-hidden rounded-lg border border-neutral-50 sm:h-96" />
        <button
            v-if="ready"
            type="button"
            class="absolute left-2.5 top-2.5 z-10 flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-white text-neutral-700 shadow-sm transition-colors hover:text-accent-500"
            aria-label="Re-center map"
            @click="recenter"
        >
            <Icon :icon="CenterFocusIcon" class="size-4" />
        </button>
    </div>
</template>
