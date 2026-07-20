<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { loadMaplibre, resolveColor, greatCircle, iataLabel, mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    // [{ origin: { lat, lng, iata }, destination: { lat, lng, iata } }]
    routes: { type: Array, required: true },
    color: { type: String, default: 'var(--color-flight)' },
    // Break out full-bleed (true) or sit within the content column (false).
    bleed: { type: Boolean, default: true },
});

const FIT_OPTIONS = { padding: 64, maxZoom: 7 };

const container = ref(null);
const showLabels = ref(false);
const ready = ref(false);

// Full-bleed box vs a contained box aligned to the surrounding text column.
const layoutClass = computed(() =>
    props.bleed
        ? 'full-width border-y md:full-width-inset md:rounded-lg md:border-x'
        : 'rounded-lg border',
);
let map = null;
let markers = [];
let savedBounds = null;
let stopThemeWatch;

const { resolved } = useTheme();

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

function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
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

    // Adds the arc + endpoint sources/layers; re-run after setStyle since
    // maplibre drops custom sources/layers whenever the style is replaced.
    function addRouteLayers() {
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
    }

    map = new maplibregl.Map({
        container: container.value,
        style: mapStyleForTheme(resolved.value),
        bounds,
        fitBoundsOptions: FIT_OPTIONS,
        attributionControl: false,
    });

    savedBounds = bounds;
    ready.value = true;

    map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

    endpoints.forEach((point) => {
        if (point.iata) {
            markers.push(iataLabel(maplibregl, point).addTo(map));
        }
    });

    map.on('load', addRouteLayers);

    // Swap the basemap on colour-scheme change, carrying the custom layers
    // across so they stay drawn (setStyle would otherwise drop them).
    stopThemeWatch = watch(resolved, (value) => {
        if (!map) {
            return;
        }

        swapBasemapStyle(map, mapStyleForTheme(value), ['arcs', 'endpoints']);
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
    markers.forEach((marker) => marker.remove());
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="flights-map relative overflow-hidden border-neutral-50" :class="layoutClass">
        <div ref="container" class="size-full" />
        <div class="absolute left-2.5 top-2.5 z-10 flex items-center gap-2">
            <button
                v-if="ready"
                type="button"
                class="flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                aria-label="Re-center map"
                @click="recenter"
            >
                <Icon name="CenterFocusIcon" class="size-4" />
            </button>
            <button
                type="button"
                class="rounded-md border border-neutral-100 bg-neutral-0/95 px-2.5 py-1.5 text-label font-semibold text-neutral-700 shadow-card transition-colors hover:text-accent-500"
                :aria-pressed="showLabels"
                @click="showLabels = !showLabels"
            >
                {{ showLabels ? 'Hide labels' : 'Show labels' }}
            </button>
        </div>
    </div>
</template>

<style scoped>
.flights-map {
    height: clamp(20rem, 48vh, 32rem);
}
</style>
