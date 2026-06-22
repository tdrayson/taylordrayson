<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { loadMaplibre, resolveColor, greatCircle, iataLabel, OPENFREEMAP_POSITRON } from '../maplibre.js';

const props = defineProps({
    // [{ origin: { lat, lng, iata }, destination: { lat, lng, iata } }]
    routes: { type: Array, required: true },
    color: { type: String, default: 'var(--color-flight)' },
});

const container = ref(null);
const showLabels = ref(true);
let map = null;
let markers = [];

/** Build great-circle arcs and the unique set of endpoints across every route. */
function buildGeometry() {
    const arcs = [];
    const endpoints = new Map();

    props.routes.forEach((route) => {
        const from = { lat: Number(route.origin.lat), lng: Number(route.origin.lng), iata: route.origin.iata };
        const to = { lat: Number(route.destination.lat), lng: Number(route.destination.lng), iata: route.destination.iata };

        if ([from.lat, from.lng, to.lat, to.lng].some(Number.isNaN)) {
            return;
        }

        arcs.push(greatCircle(from, to));
        [from, to].forEach((point) => endpoints.set(point.iata ?? `${point.lat},${point.lng}`, point));
    });

    return { arcs, endpoints: [...endpoints.values()] };
}

watch(showLabels, (show) => {
    markers.forEach((marker) => {
        marker.getElement().style.display = show ? '' : 'none';
    });
});

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    const { arcs, endpoints } = buildGeometry();

    if (arcs.length === 0) {
        return;
    }

    const color = resolveColor(props.color);

    const bounds = arcs.flat().reduce(
        (box, coordinate) => box.extend(coordinate),
        new maplibregl.LngLatBounds(arcs[0][0], arcs[0][0]),
    );

    map = new maplibregl.Map({
        container: container.value,
        style: OPENFREEMAP_POSITRON,
        bounds,
        fitBoundsOptions: { padding: 64, maxZoom: 7 },
        attributionControl: false,
    });

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    endpoints.forEach((point) => {
        if (point.iata) {
            markers.push(iataLabel(maplibregl, point).addTo(map));
        }
    });

    map.on('load', () => {
        map.addSource('arcs', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: arcs.map((arc) => ({
                    type: 'Feature',
                    geometry: { type: 'LineString', coordinates: arc },
                })),
            },
        });

        map.addLayer({
            id: 'arcs',
            type: 'line',
            source: 'arcs',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': color, 'line-width': 1.8, 'line-opacity': 0.55, 'line-dasharray': [2, 1.6] },
        });

        map.addSource('endpoints', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: endpoints.map((point) => ({
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
                'circle-radius': 4,
                'circle-color': '#ffffff',
                'circle-stroke-color': color,
                'circle-stroke-width': 2.5,
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
    <div class="flights-map full-width relative overflow-hidden border-y border-line-2 md:full-width-inset md:rounded-lg md:border-x">
        <div ref="container" class="size-full" />
        <button
            type="button"
            class="absolute left-3 top-3 z-10 rounded-md border border-line bg-canvas/95 px-2.5 py-1.5 text-label font-semibold text-ink-2 shadow-card transition-colors hover:text-accent"
            :aria-pressed="showLabels"
            @click="showLabels = !showLabels"
        >
            {{ showLabels ? 'Hide labels' : 'Show labels' }}
        </button>
    </div>
</template>

<style scoped>
.flights-map {
    height: clamp(26rem, 65vh, 48rem);
}
</style>
