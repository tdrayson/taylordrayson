<script setup>
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';
import PhotoMarker from './PhotoMarker.vue';

const props = defineProps({
    polyline: { type: String, required: true },
    color: { type: String, default: '#3858e9' },
    heightClass: { type: String, default: 'h-72 sm:h-96' },
    photos: { type: Array, default: () => [] },
    // Track points ({time, lat, lng}) for the scrub dot; empty on non-activity
    // maps and until the deferred activity profile prop resolves.
    track: { type: Array, default: () => [] },
    // Shared cursor from useActivityCursor; EntryMap only reads its fraction to
    // position the dot (the activity profile charts drive it). Null means no dot.
    cursor: { type: Object, default: null },
});

const emit = defineEmits(['open-photo']);

const MAPLIBRE_VERSION = '4.7.1';

const { resolved } = useTheme();

// Photos that have a coordinate, each keeping its index in the ORIGINAL photos
// array. The Lightbox opens by that index, so mapping must happen before
// filtering: filtering first would renumber them and open the wrong photo.
const locatedPhotos = computed(() =>
    props.photos
        .map((photo, index) => ({ ...photo, index }))
        .filter((photo) => photo.latitude !== null && photo.longitude !== null),
);

// maxZoom caps how far fit-to-route zooms in, so short, tightly-clustered
// activities (e.g. padel) keep surrounding map context instead of filling the
// frame with an unreadable scribble.
const FIT_OPTIONS = { padding: 48, maxZoom: 17 };

const container = ref(null);
const ready = ref(false);
const markerRefs = ref([]);
let map = null;
let savedBounds = null;
let stopThemeWatch;
let markers = [];
let stopTrackWatch;
let stopCursorWatch;
let routeDot = null;

// Re-fit the view to the route's bounds after the visitor has panned or zoomed.
function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
}

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

    return getComputedStyle(document.documentElement).getPropertyValue(match[1]).trim() || '#3858e9';
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

    // Builds the dot marker the first time a non-empty track arrives, so the
    // element exists in the DOM (hidden) even before the visitor scrubs a chart.
    function ensureRouteDot() {
        if (routeDot || props.track.length === 0) {
            return;
        }

        const element = document.createElement('div');
        element.dataset.testid = 'route-dot';
        element.className = 'invisible size-3 rounded-full border-2 border-neutral-0';
        element.style.backgroundColor = resolveColor(props.color);
        // The dot is purely a readout of the chart cursor, so it must not capture
        // pointer events meant for the map beneath it (panning, photo markers).
        element.style.pointerEvents = 'none';
        // MapLibre gives DOM markers no z-index, so they stack by insertion order.
        // The dot is created lazily (once the deferred track arrives) after the
        // photo markers, which would otherwise leave it on top. Pin it below the
        // photos explicitly so the images always sit above the scrub dot.
        element.style.zIndex = '1';

        routeDot = new maplibregl.Marker({ element }).setLngLat([props.track[0].lng, props.track[0].lat]).addTo(map);
    }

    // Moves the dot to the cursor's current track point, hiding it whenever
    // there's no active index (pointer left, or the track hasn't loaded yet).
    function updateRouteDot() {
        ensureRouteDot();

        if (!routeDot) {
            return;
        }

        // Map the shared 0..1 fraction to this track's own nearest index.
        const fraction = props.cursor?.fraction?.value;
        const index = fraction != null && props.track.length > 0
            ? Math.round(fraction * (props.track.length - 1))
            : null;
        const point = index != null ? props.track[index] : null;

        if (!point) {
            routeDot.getElement().classList.add('invisible');

            return;
        }

        routeDot.setLngLat([point.lng, point.lat]);
        routeDot.getElement().classList.remove('invisible');
    }

    // Adds the route source/layer; re-run after setStyle since maplibre
    // drops custom sources/layers whenever the style is replaced.
    function addRouteLayer() {
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
    }

    // Attach a MapLibre marker per located photo. Strava gives [lat, lng] and
    // maplibre wants [lng, lat], so the pair flips here and only here.
    function addPhotoMarkers() {
        locatedPhotos.value.forEach((photo, position) => {
            const element = markerRefs.value[position];

            if (!element) {
                return;
            }

            // Sit above the scrub dot (z-index 1) regardless of insertion order.
            element.style.zIndex = '2';

            markers.push(
                new maplibregl.Marker({ element })
                    .setLngLat([photo.longitude, photo.latitude])
                    .addTo(map),
            );
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

    addPhotoMarkers();

    map.on('error', (event) => console.error('[EntryMap] MapLibre error', event?.error || event));

    map.on('load', addRouteLayer);

    // Build/hide the dot for whatever track + cursor state already exists,
    // then keep it in sync as the deferred track arrives and the cursor moves.
    updateRouteDot();
    stopTrackWatch = watch(() => props.track, updateRouteDot);

    if (props.cursor) {
        stopCursorWatch = watch(props.cursor.fraction, updateRouteDot);
    }

    // Swap the basemap on colour-scheme change, carrying the route layer across
    // so it stays drawn (setStyle would otherwise drop it).
    stopThemeWatch = watch(resolved, (value) => {
        if (!map) {
            return;
        }

        swapBasemapStyle(map, mapStyleForTheme(value), ['route']);
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
    markers.forEach((marker) => marker.remove());
    markers = [];
    stopTrackWatch?.();
    stopCursorWatch?.();
    routeDot?.remove();
    routeDot = null;
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="relative">
        <div ref="container" class="w-full overflow-hidden rounded-lg border border-neutral-50" :class="heightClass" />
        <button
            v-if="ready"
            type="button"
            class="absolute left-2.5 top-2.5 z-10 flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
            aria-label="Re-center map"
            @click="recenter"
        >
            <Icon name="CenterFocusIcon" class="size-4" />
        </button>

        <div class="hidden">
            <PhotoMarker
                v-for="(photo, position) in locatedPhotos"
                :key="photo.index"
                :ref="(el) => (markerRefs[position] = el?.$el)"
                :photo="photo"
                :label="`View photo ${photo.index + 1}`"
                @select="emit('open-photo', photo.index)"
            />
        </div>
    </div>
</template>
