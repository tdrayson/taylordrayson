<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    polyline: { type: String, required: true },
    color: { type: String, default: '#ff385c' },
});

const MAPLIBRE_VERSION = '4.7.1';
const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

const container = ref(null);
let map = null;

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

    return getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() || '#ff385c';
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
        fitBoundsOptions: { padding: 48 },
        attributionControl: false,
    });

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
    <div ref="container" class="h-72 w-full overflow-hidden rounded-lg border border-line-2 sm:h-96" />
</template>
