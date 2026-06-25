<script setup>
import { onMounted, onBeforeUnmount, ref } from 'vue';
import { loadMaplibre, resolveColor, greatCircle, iataLabel, OPENFREEMAP_POSITRON } from '../../lib/maplibre.js';

const props = defineProps({
    origin: { type: Object, required: true }, // { lat, lng }
    destination: { type: Object, required: true }, // { lat, lng }
    color: { type: String, default: '#3858e9' },
});

const container = ref(null);
let map = null;
let markers = [];

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    const from = { lat: Number(props.origin.lat), lng: Number(props.origin.lng), iata: props.origin.iata };
    const to = { lat: Number(props.destination.lat), lng: Number(props.destination.lng), iata: props.destination.iata };

    if ([from.lat, from.lng, to.lat, to.lng].some(Number.isNaN)) {
        return;
    }

    const arc = greatCircle(from, to);
    const color = resolveColor(props.color);

    const bounds = arc.reduce(
        (box, coordinate) => box.extend(coordinate),
        new maplibregl.LngLatBounds(arc[0], arc[0]),
    );

    map = new maplibregl.Map({
        container: container.value,
        style: OPENFREEMAP_POSITRON,
        bounds,
        fitBoundsOptions: { padding: 56, maxZoom: 7 },
        attributionControl: false,
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    [from, to].forEach((point) => {
        if (point.iata) {
            markers.push(iataLabel(maplibregl, point).addTo(map));
        }
    });

    map.on('load', () => {
        map.addSource('arc', {
            type: 'geojson',
            data: { type: 'Feature', geometry: { type: 'LineString', coordinates: arc } },
        });

        map.addLayer({
            id: 'arc',
            type: 'line',
            source: 'arc',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': color, 'line-width': 2.5, 'line-dasharray': [2, 1.6] },
        });

        map.addSource('endpoints', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: [from, to].map((point) => ({
                    type: 'Feature',
                    geometry: { type: 'Point', coordinates: [point.lng, point.lat] },
                })),
            },
        });

        map.addLayer({
            id: 'endpoints',
            type: 'circle',
            source: 'endpoints',
            paint: {
                'circle-radius': 5,
                'circle-color': '#ffffff',
                'circle-stroke-color': color,
                'circle-stroke-width': 3,
            },
        });
    });
});

onBeforeUnmount(() => {
    markers.forEach((marker) => marker.remove());
    map?.remove();
});
</script>

<template>
    <div ref="container" class="h-72 w-full overflow-hidden rounded-lg border border-neutral-50 sm:h-96" />
</template>
