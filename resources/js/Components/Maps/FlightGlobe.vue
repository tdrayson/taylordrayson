<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import { applyGlobe, iataLabel, loadMaplibre, resolveColor, greatCircle, mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

/**
 * A full-screen 3D globe plotting every flight as a great-circle arc.
 * @param {Array} entries FlightMapEntry records: { id, year, origin: { iata, lat, lng }, destination: { iata, lat, lng }, ... }.
 * @param {number|null} year When set, only arcs and endpoints for that year are shown; null shows all-time.
 * @param {number|null} selectedId Flight id to hold highlighted regardless of hover.
 * @param {number|null} hoveredId Flight id to highlight; takes precedence over selectedId.
 * @param {string} color Arc and endpoint colour, as a CSS value or a `var(--token)` reference.
 */
const props = defineProps({
    entries: { type: Array, required: true },
    year: { type: Number, default: null },
    selectedId: { type: Number, default: null },
    hoveredId: { type: Number, default: null },
    color: { type: String, default: 'var(--color-flight)' },
});

const emit = defineEmits(['select']);

const FIT_OPTIONS = { padding: 64, maxZoom: 7 };

// The sidebar list built in a later task is the accessible representation
// of this data, so camera moves can jump for anyone who prefers less motion.
const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

const container = ref(null);

let map = null;
let markers = [];
let flights = [];
let maplibregl = null;
let stopThemeWatch;

const { resolved } = useTheme();

/** Drop entries with non-numeric coordinates, coercing lat/lng to numbers. */
function toValidFlights(rawEntries) {
    return rawEntries
        .map((entry) => ({
            ...entry,
            origin: { ...entry.origin, lat: Number(entry.origin.lat), lng: Number(entry.origin.lng) },
            destination: { ...entry.destination, lat: Number(entry.destination.lat), lng: Number(entry.destination.lng) },
        }))
        .filter((entry) => ![entry.origin.lat, entry.origin.lng, entry.destination.lat, entry.destination.lng].some(Number.isNaN));
}

/** Collapse flights down to one label point per airport, tracking which years it flew in. */
function uniqueLabelPoints(entries) {
    const byIata = new Map();

    entries.forEach((entry) => {
        [entry.origin, entry.destination].forEach((point) => {
            if (!point.iata) {
                return;
            }

            if (!byIata.has(point.iata)) {
                byIata.set(point.iata, { iata: point.iata, lat: point.lat, lng: point.lng, years: new Set() });
            }

            byIata.get(point.iata).years.add(entry.year);
        });
    });

    return [...byIata.values()];
}

/**
 * Filter the map to a single year, or clear the filter for all-time, then
 * re-frame the globe on whatever is now visible. setFilter (not setData or
 * addLayer) keeps this to a style update rather than rebuilding the source.
 */
function applyYear(year) {
    if (!map) {
        return;
    }

    const filter = year === null ? null : ['==', ['get', 'year'], year];
    map.setFilter('arcs', filter);
    map.setFilter('endpoints', filter);

    markers.forEach(({ marker, years }) => {
        marker.getElement().style.display = year === null || years.has(year) ? '' : 'none';
    });

    const relevant = year === null ? flights : flights.filter((entry) => entry.year === year);

    if (relevant.length === 0) {
        return;
    }

    const bounds = relevant.reduce(
        (box, entry) => box.extend([entry.origin.lng, entry.origin.lat]).extend([entry.destination.lng, entry.destination.lat]),
        new maplibregl.LngLatBounds([relevant[0].origin.lng, relevant[0].origin.lat], [relevant[0].origin.lng, relevant[0].origin.lat]),
    );

    map.fitBounds(bounds, { ...FIT_OPTIONS, animate: !reduceMotion });
}

/**
 * Reflect hoveredId/selectedId onto arc feature-state so the paint expression
 * highlights or dims arcs without touching layers. Hover wins over selection.
 */
function updateFeatureState() {
    if (!map || !map.getSource('arcs')) {
        return;
    }

    map.removeFeatureState({ source: 'arcs' });

    const activeId = props.hoveredId ?? props.selectedId;

    if (activeId === null) {
        return;
    }

    map.setFeatureState({ source: 'arcs', id: activeId }, { active: true });

    flights.forEach((entry) => {
        if (entry.id !== activeId) {
            map.setFeatureState({ source: 'arcs', id: entry.id }, { dimmed: true });
        }
    });
}

watch(() => props.year, applyYear);
watch(() => [props.hoveredId, props.selectedId], updateFeatureState);

onMounted(async () => {
    maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    flights = toValidFlights(props.entries);

    if (flights.length === 0) {
        return;
    }

    const color = resolveColor(props.color);

    const arcFeatures = flights.map((entry) => ({
        type: 'Feature',
        id: entry.id,
        properties: { id: entry.id, year: entry.year },
        geometry: { type: 'LineString', coordinates: greatCircle(entry.origin, entry.destination) },
    }));

    const endpointFeatures = flights.flatMap((entry) => [entry.origin, entry.destination].map((point) => ({
        type: 'Feature',
        properties: { year: entry.year },
        geometry: { type: 'Point', coordinates: [point.lng, point.lat] },
    })));

    const bounds = arcFeatures.reduce(
        (box, feature) => feature.geometry.coordinates.reduce((b, coordinate) => b.extend(coordinate), box),
        new maplibregl.LngLatBounds(arcFeatures[0].geometry.coordinates[0], arcFeatures[0].geometry.coordinates[0]),
    );

    // Adds the arc + endpoint sources/layers; re-run after setStyle since
    // maplibre drops custom sources/layers whenever the style is replaced.
    function addRouteLayers() {
        map.addSource('arcs', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: arcFeatures },
        });

        map.addLayer({
            id: 'arcs',
            type: 'line',
            source: 'arcs',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: {
                'line-color': color,
                'line-width': ['case', ['boolean', ['feature-state', 'active'], false], 3.2, 1.8],
                'line-opacity': ['case',
                    ['boolean', ['feature-state', 'active'], false], 1,
                    ['boolean', ['feature-state', 'dimmed'], false], 0.15,
                    0.55,
                ],
            },
        });

        map.addSource('endpoints', {
            type: 'geojson',
            data: { type: 'FeatureCollection', features: endpointFeatures },
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

    uniqueLabelPoints(flights).forEach((point) => {
        markers.push({
            marker: iataLabel(maplibregl, point, { locationOccludedOpacity: 0 }).addTo(map),
            years: point.years,
        });
    });

    map.on('load', () => {
        applyGlobe(map, resolved.value);
        addRouteLayers();
        applyYear(props.year);
        updateFeatureState();

        map.on('mouseenter', 'arcs', () => {
            map.getCanvas().style.cursor = 'pointer';
        });

        map.on('mouseleave', 'arcs', () => {
            map.getCanvas().style.cursor = '';
        });

        // A single handler for both cases (arc hit vs background) avoids the
        // double-fire that comes from also binding a layer-specific listener.
        map.on('click', (e) => {
            const hits = map.queryRenderedFeatures(e.point, { layers: ['arcs'] });
            emit('select', hits.length > 0 ? hits[0].properties.id : null);
        });
    });

    // Swap the basemap on colour-scheme change, carrying the custom layers
    // across so they stay drawn (setStyle would otherwise drop them).
    stopThemeWatch = watch(resolved, (value) => {
        if (!map) {
            return;
        }

        swapBasemapStyle(map, mapStyleForTheme(value), ['arcs', 'endpoints']);
        // setStyle drops projection and sky, so re-apply once the new style settles.
        map.once('styledata', () => applyGlobe(map, value));
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
    markers.forEach(({ marker }) => marker.remove());
    map?.remove();
    map = null;
});
</script>

<template>
    <div ref="container" class="size-full" aria-hidden="true" />
</template>
