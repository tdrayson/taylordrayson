<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { loadMaplibre, resolveColor, mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    // [{ lat, lng, label }]
    places: { type: Array, required: true },
    color: { type: String, default: 'var(--color-checkin)' },
    // Break out full-bleed (true) or sit within the content column (false).
    bleed: { type: Boolean, default: true },
});

const FIT_OPTIONS = { padding: 64, maxZoom: 12 };
const SOURCE = 'places';
// Source + every layer, carried across a basemap style swap (setStyle drops them).
const CARRIED = [SOURCE, 'clusters', 'cluster-count', 'unclustered-point'];

const container = ref(null);
const ready = ref(false);

const layoutClass = computed(() =>
    props.bleed
        ? 'full-width border-y md:full-width-inset md:rounded-lg md:border-x'
        : 'rounded-lg border',
);

let map = null;
let popup = null;
let savedBounds = null;
let stopThemeWatch;

const { resolved } = useTheme();

// Places with valid coordinates only.
function buildPlaces() {
    return props.places
        .map((place) => ({ lat: Number(place.lat), lng: Number(place.lng), label: place.label || '' }))
        .filter((place) => !Number.isNaN(place.lat) && !Number.isNaN(place.lng));
}

function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
}

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    const places = buildPlaces();

    if (places.length === 0) {
        return;
    }

    const color = resolveColor(props.color);
    // The dot's fill contrasts the ring against the basemap in each theme.
    const innerColor = () => (resolved.value === 'dark' ? '#0a0a0a' : '#ffffff');

    const featureCollection = {
        type: 'FeatureCollection',
        features: places.map((place) => ({
            type: 'Feature',
            properties: { label: place.label },
            geometry: { type: 'Point', coordinates: [place.lng, place.lat] },
        })),
    };

    const bounds = places.reduce(
        (box, place) => box.extend([place.lng, place.lat]),
        new maplibregl.LngLatBounds([places[0].lng, places[0].lat], [places[0].lng, places[0].lat]),
    );

    // Clustered source and its layers; re-run after a style swap since maplibre
    // drops custom sources/layers whenever the basemap style is replaced.
    function addPlaceLayers() {
        map.addSource(SOURCE, {
            type: 'geojson',
            data: featureCollection,
            cluster: true,
            clusterMaxZoom: 14,
            clusterRadius: 50,
        });

        map.addLayer({
            id: 'clusters',
            type: 'circle',
            source: SOURCE,
            filter: ['has', 'point_count'],
            paint: {
                'circle-color': color,
                'circle-opacity': 0.85,
                // Grow the bubble in steps as the cluster gets bigger.
                'circle-radius': ['step', ['get', 'point_count'], 16, 25, 22, 100, 30],
            },
        });

        map.addLayer({
            id: 'cluster-count',
            type: 'symbol',
            source: SOURCE,
            filter: ['has', 'point_count'],
            layout: {
                'text-field': ['get', 'point_count_abbreviated'],
                'text-font': ['Noto Sans Bold'],
                'text-size': 12,
            },
            paint: { 'text-color': '#ffffff' },
        });

        map.addLayer({
            id: 'unclustered-point',
            type: 'circle',
            source: SOURCE,
            filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-radius': 6,
                'circle-color': innerColor(),
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
    popup = new maplibregl.Popup({ closeButton: false, offset: 12 });

    map.on('load', () => {
        addPlaceLayers();

        // Click a cluster to zoom in to the level where it breaks apart.
        map.on('click', 'clusters', (event) => {
            const feature = event.features?.[0];
            const clusterId = feature?.properties?.cluster_id;

            if (clusterId == null) {
                return;
            }

            map.getSource(SOURCE).getClusterExpansionZoom(clusterId).then((zoom) => {
                map.easeTo({ center: feature.geometry.coordinates, zoom });
            });
        });

        // Click a single place to name it.
        map.on('click', 'unclustered-point', (event) => {
            const feature = event.features?.[0];
            const label = feature?.properties?.label;

            if (label) {
                popup.setLngLat(feature.geometry.coordinates).setText(label).addTo(map);
            }
        });

        for (const layer of ['clusters', 'unclustered-point']) {
            map.on('mouseenter', layer, () => {
                map.getCanvas().style.cursor = 'pointer';
            });
            map.on('mouseleave', layer, () => {
                map.getCanvas().style.cursor = '';
            });
        }
    });

    stopThemeWatch = watch(resolved, (value) => {
        if (map) {
            swapBasemapStyle(map, mapStyleForTheme(value), CARRIED);
        }
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
    popup?.remove();
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="places-map relative overflow-hidden border-neutral-50" :class="layoutClass">
        <div ref="container" class="size-full" />
        <div class="absolute left-2.5 top-2.5 z-10">
            <button
                v-if="ready"
                type="button"
                class="flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                aria-label="Re-center map"
                @click="recenter"
            >
                <Icon name="CenterFocusIcon" class="size-4" />
            </button>
        </div>
    </div>
</template>

<style scoped>
.places-map {
    height: clamp(20rem, 48vh, 32rem);
}
</style>
