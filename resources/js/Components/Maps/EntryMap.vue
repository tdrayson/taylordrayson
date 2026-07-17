<script setup>
import { onMounted, onBeforeUnmount, ref, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { CenterFocusIcon } from '@hugeicons-pro/core-stroke-rounded';
import { mapStyleForTheme } from '../../lib/maplibre.js';
import { useTheme } from '../../useTheme.js';

const props = defineProps({
    polyline: { type: String, required: true },
    color: { type: String, default: '#3858e9' },
    heightClass: { type: String, default: 'h-72 sm:h-96' },
    // Track points ({time, lat, lng}) for the scrub dot; empty on non-activity
    // maps and until the deferred activity profile prop resolves.
    track: { type: Array, default: () => [] },
    // Shared cursor from useActivityCursor (index ref + set/clear); null on
    // maps that don't wire one up, which keeps the dot/scrub fully inert.
    cursor: { type: Object, default: null },
});

const MAPLIBRE_VERSION = '4.7.1';

const { resolved } = useTheme();

// maxZoom caps how far fit-to-route zooms in, so short, tightly-clustered
// activities (e.g. padel) keep surrounding map context instead of filling the
// frame with an unreadable scribble.
const FIT_OPTIONS = { padding: 48, maxZoom: 17 };

const container = ref(null);
const ready = ref(false);
let map = null;
let savedBounds = null;
let stopThemeWatch;
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

// Nearest track point to a pointer's lngLat, by simple squared-distance scan
// (tracks top out around a few hundred points, so this stays cheap).
function nearestTrackIndex(lngLat) {
    let nearestIndex = 0;
    let nearestDistance = Infinity;

    props.track.forEach((point, index) => {
        const dLat = point.lat - lngLat.lat;
        const dLng = point.lng - lngLat.lng;
        const distance = (dLat * dLat) + (dLng * dLng);

        if (distance < nearestDistance) {
            nearestDistance = distance;
            nearestIndex = index;
        }
    });

    return nearestIndex;
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

    // Builds the scrub-dot marker the first time a non-empty track arrives, so
    // the element exists in the DOM (hidden) even before the visitor hovers.
    function ensureRouteDot() {
        if (routeDot || props.track.length === 0) {
            return;
        }

        const element = document.createElement('div');
        element.dataset.testid = 'route-dot';
        element.className = 'invisible size-3 rounded-full border-2 border-neutral-0';
        element.style.backgroundColor = resolveColor(props.color);
        // Let pointer moves that land on the dot pass through to the map canvas,
        // so scrubbing never stalls when the pointer is over the dot itself.
        element.style.pointerEvents = 'none';

        routeDot = new maplibregl.Marker({ element }).setLngLat([props.track[0].lng, props.track[0].lat]).addTo(map);
    }

    // Moves the dot to the cursor's current track point, hiding it whenever
    // there's no active index (pointer left, or the track hasn't loaded yet).
    function updateRouteDot() {
        ensureRouteDot();

        if (!routeDot) {
            return;
        }

        const index = props.cursor?.index?.value;
        const point = index != null ? props.track[index] : null;

        if (!point) {
            routeDot.getElement().classList.add('invisible');

            return;
        }

        routeDot.setLngLat([point.lng, point.lat]);
        routeDot.getElement().classList.remove('invisible');
    }

    // Scrub handler shared by mouse and touch move: drive the shared cursor
    // to whichever track point is nearest the pointer.
    function onRouteMove(event) {
        if (!props.cursor || props.track.length === 0) {
            return;
        }

        props.cursor.set(nearestTrackIndex(event.lngLat));
    }

    // Clear the shared cursor when the pointer leaves the map (mouse) or lifts (touch).
    function onRouteLeave() {
        props.cursor?.clear();
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

    map.on('error', (event) => console.error('[EntryMap] MapLibre error', event?.error || event));

    map.on('load', addRouteLayer);

    // Route scrub: mouse + touch move both drive the shared cursor; mouseout
    // (pointer) and touchend (finger lift) both clear it. No-ops when this
    // map has no cursor/track wired up (non-activity uses of EntryMap).
    map.on('mousemove', onRouteMove);
    map.on('touchmove', onRouteMove);
    map.on('mouseout', onRouteLeave);
    map.on('touchend', onRouteLeave);

    // Build/hide the dot for whatever track + cursor state already exists,
    // then keep it in sync as the deferred track arrives and the cursor moves.
    updateRouteDot();
    stopTrackWatch = watch(() => props.track, updateRouteDot);

    if (props.cursor) {
        stopCursorWatch = watch(props.cursor.index, updateRouteDot);
    }

    // Switch basemap when the colour scheme changes, then re-add the custom
    // layer once the new style has finished loading (setStyle clears it).
    stopThemeWatch = watch(resolved, (value) => {
        if (!map) {
            return;
        }

        map.setStyle(mapStyleForTheme(value));
        // Dedupe: drop any pending re-add from a previous toggle before
        // registering a fresh one, otherwise rapid toggles stack handlers
        // and both fire, throwing on the second addSource/addLayer call.
        map.off('style.load', addRouteLayer);
        map.once('style.load', addRouteLayer);
    });
});

onBeforeUnmount(() => {
    stopThemeWatch?.();
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
            <Icon :icon="CenterFocusIcon" class="size-4" />
        </button>
    </div>
</template>
