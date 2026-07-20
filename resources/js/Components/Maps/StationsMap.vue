<script setup>
import { computed, onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { loadMaplibre, resolveColor, placeLabel, mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    // [{ lat, lng, label }]
    stations: { type: Array, required: true },
    color: { type: String, default: 'var(--color-fuel)' },
    // Break out full-bleed (true) or sit within the content column (false).
    bleed: { type: Boolean, default: true },
});

const FIT_OPTIONS = { padding: 64, maxZoom: 12 };

const container = ref(null);
const showLabels = ref(false);
const ready = ref(false);

const layoutClass = computed(() =>
    props.bleed
        ? 'full-width border-y md:full-width-inset md:rounded-lg md:border-x'
        : 'rounded-lg border',
);

let map = null;
/** @type {Map<string, import('maplibre-gl').Marker>} */
const markersByKey = new Map();
let selectedKey = null;
let savedBounds = null;
let stopThemeWatch;

const { resolved } = useTheme();

function stationKey(station) {
    return `${station.lng},${station.lat}`;
}

/** Unique stations with valid coordinates. */
function buildStations() {
    return props.stations
        .map((station) => ({
            lat: Number(station.lat),
            lng: Number(station.lng),
            label: station.label || '',
        }))
        .filter((station) => !Number.isNaN(station.lat) && !Number.isNaN(station.lng));
}

function syncLabelVisibility() {
    markersByKey.forEach((marker, key) => {
        const visible = showLabels.value || key === selectedKey;
        marker.getElement().style.display = visible ? '' : 'none';
    });
}

function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
}

watch(showLabels, () => {
    if (showLabels.value) {
        selectedKey = null;
    }

    syncLabelVisibility();
});

onMounted(async () => {
    const maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    const stations = buildStations();

    if (stations.length === 0) {
        return;
    }

    const color = resolveColor(props.color);

    const bounds = stations.reduce(
        (box, station) => box.extend([station.lng, station.lat]),
        new maplibregl.LngLatBounds([stations[0].lng, stations[0].lat], [stations[0].lng, stations[0].lat]),
    );

    function markerInnerColor() {
        return resolved.value === 'dark' ? '#0a0a0a' : '#ffffff';
    }

    // Adds the station source/layer; re-run after setStyle since maplibre
    // drops custom sources/layers whenever the style is replaced.
    function addStationLayers() {
        map.addSource('stations', {
            type: 'geojson',
            data: {
                type: 'FeatureCollection',
                features: stations.map((station) => ({
                    type: 'Feature',
                    properties: { key: stationKey(station), label: station.label },
                    geometry: { type: 'Point', coordinates: [station.lng, station.lat] },
                })),
            },
        });

        map.addLayer({
            id: 'stations',
            type: 'circle',
            source: 'stations',
            paint: {
                'circle-radius': 6,
                'circle-color': markerInnerColor(),
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

    stations.forEach((station) => {
        if (!station.label) {
            return;
        }

        const key = stationKey(station);
        const marker = placeLabel(maplibregl, station, station.label).addTo(map);
        marker.getElement().style.display = 'none';
        markersByKey.set(key, marker);
    });

    map.on('load', () => {
        addStationLayers();

        map.on('mouseenter', 'stations', () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', 'stations', () => {
            map.getCanvas().style.cursor = '';
        });

        // Click a pin to reveal its label; click again (or elsewhere) to hide.
        map.on('click', 'stations', (event) => {
            if (showLabels.value) {
                return;
            }

            const key = event.features?.[0]?.properties?.key;

            if (!key || !markersByKey.has(key)) {
                return;
            }

            selectedKey = selectedKey === key ? null : key;
            syncLabelVisibility();
            event.originalEvent?.stopPropagation();
        });

        map.on('click', (event) => {
            if (showLabels.value || selectedKey === null) {
                return;
            }

            const hits = map.queryRenderedFeatures(event.point, { layers: ['stations'] });

            if (hits.length === 0) {
                selectedKey = null;
                syncLabelVisibility();
            }
        });
    });

    stopThemeWatch = watch(resolved, (value) => {
        if (!map) {
            return;
        }

        swapBasemapStyle(map, mapStyleForTheme(value), ['stations']);
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
    markersByKey.forEach((marker) => marker.remove());
    markersByKey.clear();
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="stations-map relative overflow-hidden border-neutral-50" :class="layoutClass">
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
.stations-map {
    height: clamp(20rem, 48vh, 32rem);
}
</style>
