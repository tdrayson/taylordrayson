<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { loadMaplibre, resolveColor, placeLabel, mapStyleForTheme } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

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

const { resolved } = useTheme();

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl || !container.value) {
        return;
    }

    const color = resolveColor(props.color);

    // Adds the point source/layer; re-run after setStyle since maplibre
    // drops custom sources/layers whenever the style is replaced.
    function addPlaceLayer() {
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
    }

    map = new maplibregl.Map({
        container: container.value,
        style: mapStyleForTheme(resolved.value),
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

    map.on('load', addPlaceLayer);

    // Switch basemap when the colour scheme changes, then re-add the custom
    // layer once the new style has finished loading (setStyle clears it).
    watch(resolved, (value) => {
        map.setStyle(mapStyleForTheme(value));
        map.once('style.load', addPlaceLayer);
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
