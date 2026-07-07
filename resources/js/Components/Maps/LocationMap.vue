<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';

const props = defineProps({
    lat: { type: Number, required: true },
    lng: { type: Number, required: true },
    label: { type: String, default: '' },
    zoom: { type: Number, default: 14 },
    heightClass: { type: String, default: 'h-64 sm:h-80' },
});

const MAPLIBRE_VERSION = '4.7.1';
const STYLE_URL = 'https://tiles.openfreemap.org/styles/positron';

const container = ref(null);
let map = null;

// Load the MapLibre stylesheet/script once, shared with EntryMap's loader pattern.
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

onMounted(async () => {
    loadStylesheet(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.css`);
    if (!window.maplibregl) {
        try {
            await loadScript(`https://unpkg.com/maplibre-gl@${MAPLIBRE_VERSION}/dist/maplibre-gl.js`);
        } catch (error) {
            console.error('[LocationMap] failed to load MapLibre', error);
            return;
        }
    }
    if (!window.maplibregl || !container.value) {
        return;
    }

    map = new window.maplibregl.Map({
        container: container.value,
        style: STYLE_URL,
        center: [props.lng, props.lat],
        zoom: props.zoom,
        attributionControl: true,
    });
    map.addControl(new window.maplibregl.NavigationControl({ showCompass: false }), 'top-right');
    new window.maplibregl.Marker({ color: '#2E9E6A' }).setLngLat([props.lng, props.lat]).addTo(map);
});

onBeforeUnmount(() => {
    map?.remove();
    map = null;
});
</script>

<template>
    <div
        ref="container"
        :class="heightClass"
        class="w-full overflow-hidden rounded-lg border border-neutral-100"
    />
</template>
