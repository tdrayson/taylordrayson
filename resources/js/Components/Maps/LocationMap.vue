<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { loadMaplibre, resolveColor, placeLabel, OPENFREEMAP_POSITRON } from '../../lib/maplibre.js';

const props = defineProps({
    lat: { type: Number, required: true },
    lng: { type: Number, required: true },
    label: { type: String, default: '' },
    color: { type: String, default: 'var(--color-accent-500)' },
    zoom: { type: Number, default: 15 },
    heightClass: { type: String, default: 'h-72 sm:h-96' },
});

const container = ref(null);
let map = null;
let marker = null;

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl || !container.value) {
        return;
    }

    const color = resolveColor(props.color);

    map = new maplibregl.Map({
        container: container.value,
        style: OPENFREEMAP_POSITRON,
        center: [props.lng, props.lat],
        zoom: props.zoom,
        // Attribution control off: its opaque corner block breaks the map's rounded corner (matches FlightMap).
        attributionControl: false,
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    // Floating pill label above the pin, mirroring the flight map's IATA markers.
    if (props.label) {
        marker = placeLabel(maplibregl, { lat: props.lat, lng: props.lng }, props.label).addTo(map);
    }

    map.on('load', () => {
        map.addSource('place', {
            type: 'geojson',
            data: { type: 'Feature', geometry: { type: 'Point', coordinates: [props.lng, props.lat] } },
        });

        map.addLayer({
            id: 'place',
            type: 'circle',
            source: 'place',
            paint: {
                'circle-radius': 6,
                'circle-color': '#ffffff',
                'circle-stroke-color': color,
                'circle-stroke-width': 3,
            },
        });
    });
});

onBeforeUnmount(() => {
    marker?.remove();
    map?.remove();
    map = null;
});
</script>

<template>
    <div
        ref="container"
        :class="heightClass"
        class="w-full overflow-hidden rounded-lg border border-neutral-50"
    />
</template>
