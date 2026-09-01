<script setup>
import { onMounted, onBeforeUnmount, ref, computed, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { loadMaplibre, mapStyleForTheme, swapBasemapStyle } from '../../lib/maplibre.js';
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

// Whether there is a route worth drawing, and whether the draw is running now
// (which is the only thing the control's play/pause face depends on).
const replayable = ref(false);
const playing = ref(false);

// The draw itself lives inside onMounted's closure, so the control reaches it
// through these, assigned once the map is up.
let startDraw = null;
let pauseDraw = null;

/** Resume or replay the route draw, or pause it if it is already running. */
function toggleReplay() {
    if (playing.value) {
        pauseDraw?.();

        return;
    }

    startDraw?.();
}

/**
 * The intro draws at a steady on-screen speed rather than a fixed duration:
 * fitBounds puts every route in the same frame, so what varies is how much
 * line is packed into it. A padel scribble holds ~79x its own bounding-box
 * diagonal in path, against ~1.1x for a straight run, hence the clamp.
 */
const DRAW_SPEED_PX_PER_MS = 0.22;
const DRAW_MIN_DURATION = 2200;
const DRAW_MAX_DURATION = 5500;

/** Breathing room after the map appears, so the draw is not half over by the
 *  time the rest of the page has settled. */
const DRAW_START_DELAY = 400;
const markerRefs = ref([]);
let map = null;
let savedBounds = null;
let stopThemeWatch;
let markers = [];
let stopTrackWatch;
let stopCursorWatch;
let routeDot = null;
let drawFrame = null;
let drawStartTimer = null;
let drawHead = null;
let startMarker = null;
let finishMarker = null;
// How many of the route's coordinates are currently drawn, which is what the
// layer is built from. Starts at zero only when the intro is going to play.
let drawn = 0;
let animating = false;
/** [{ element, at }] per located photo: the marker and where on the route it sits. */
let photoReveals = [];

// Re-fit the view to the route's bounds after the visitor has panned or zoomed.
function recenter() {
    if (map && savedBounds) {
        map.fitBounds(savedBounds, FIT_OPTIONS);
    }
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
    const maplibregl = await loadMaplibre();

    if (!maplibregl) {
        return;
    }

    const coords = decodePolyline(props.polyline);

    if (coords.length === 0) {
        console.warn('[EntryMap] polyline decoded to 0 coordinates', props.polyline?.slice(0, 30));

        return;
    }

    const bounds = coords.reduce(
        (box, coordinate) => box.extend(coordinate),
        new maplibregl.LngLatBounds(coords[0], coords[0]),
    );

    // How far through the draw we are, 0..1. Survives a pause so resuming
    // continues the line rather than restarting it.
    let progress = 0;

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
            data: routeUpTo(drawn),
        });

        map.addLayer({
            id: 'route',
            type: 'line',
            source: 'route',
            layout: { 'line-join': 'round', 'line-cap': 'round' },
            paint: { 'line-color': resolveColor(props.color), 'line-width': 3.5 },
        });
    }

    /** The route as drawn so far: the first `count` coordinates. */
    function routeUpTo(count) {
        return {
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: coords.slice(0, Math.max(count, 2)) },
        };
    }

    /**
     * Distance in screen pixels from the route's start to each of its points.
     * Stepping the draw along this rather than along the coordinate index keeps
     * the speed even: GPS points bunch up at corners and thin out on straights,
     * so an index-paced draw crawls through bends and leaps down the straights.
     *
     * @return {{ cumulative: number[], total: number }}
     */
    function measureRoute() {
        const cumulative = [0];
        let total = 0;
        let previous = map.project(coords[0]);

        for (let index = 1; index < coords.length; index++) {
            const point = map.project(coords[index]);

            total += Math.hypot(point.x - previous.x, point.y - previous.y);
            cumulative.push(total);
            previous = point;
        }

        return { cumulative, total };
    }

    /**
     * Draws the route on once, from start to finish, revealing each photo as
     * the line reaches where it was taken. Deceleration at the end stops the
     * finish feeling abrupt on a long route.
     */
    function playDraw(from = 0) {
        const { cumulative, total } = measureRoute();

        // A route with no on-screen length (every point projecting to the same
        // pixel) has nothing to animate, so it goes straight to finished.
        if (total <= 0) {
            finishDraw();

            return;
        }

        const duration = Math.min(
            Math.max(total / DRAW_SPEED_PX_PER_MS, DRAW_MIN_DURATION),
            DRAW_MAX_DURATION,
        );

        startMarker?.getElement().classList.remove('entry-map-endpoint--pending');

        // Resuming keeps the head it already had; only a fresh draw makes one.
        drawHead ??= new maplibregl.Marker({ element: drawHeadElement() })
            .setLngLat(coords[0])
            .addTo(map);

        // Wound back so `elapsed` picks up where the pause left off.
        const start = performance.now() - from * duration;
        let index = 1;

        playing.value = true;

        /**
         * The point `target` pixels along the route, interpolated inside the
         * segment holding it. Without this the line can only grow a whole
         * coordinate at a time, which on a 20-point walk is a visible step
         * every hundred milliseconds rather than a smooth draw.
         */
        function tipAt(target) {
            const from = coords[index - 1];
            const to = coords[index];
            const span = cumulative[index] - cumulative[index - 1];
            const along = span > 0 ? Math.min(Math.max((target - cumulative[index - 1]) / span, 0), 1) : 1;

            return [from[0] + (to[0] - from[0]) * along, from[1] + (to[1] - from[1]) * along];
        }

        function frame(now) {
            const elapsed = (now - start) / duration;

            progress = Math.min(elapsed, 1);
            // Gently eased rather than sharply: a cubic ease-out draws most of a
            // short route in the first third, which reads as a snap, not a draw.
            const eased = 1 - (1 - Math.min(elapsed, 1)) ** 2;
            const target = eased * total;

            // The pointer only ever moves forward, so the whole draw walks the
            // route once rather than searching it on every frame.
            while (index < cumulative.length - 1 && cumulative[index] < target) {
                index++;
            }

            const tip = tipAt(target);

            drawn = index;
            map.getSource('route')?.setData({
                type: 'Feature',
                geometry: { type: 'LineString', coordinates: [...coords.slice(0, index), tip] },
            });
            drawHead?.setLngLat(tip);
            revealPhotosUpTo(drawn);

            if (elapsed < 1) {
                drawFrame = requestAnimationFrame(frame);

                return;
            }

            finishDraw();
            drawFrame = null;
        }

        drawFrame = requestAnimationFrame(frame);
    }

    /** Show the whole route and every photo on it, and retire the head dot. */
    function finishDraw() {
        progress = 1;
        playing.value = false;
        drawn = coords.length;
        map.getSource('route')?.setData(routeUpTo(drawn));
        revealPhotosUpTo(drawn);
        startMarker?.getElement().classList.remove('entry-map-endpoint--pending');
        finishMarker?.getElement().classList.remove('entry-map-endpoint--pending');
        retireDrawHead();
    }

    /** Put the map back to how it looked before the draw, ready to run again. */
    function resetDraw() {
        progress = 0;
        drawn = 0;
        map.getSource('route')?.setData(routeUpTo(drawn));
        photoReveals.forEach(({ element }) => element.classList.add('entry-map-photo', 'entry-map-photo--pending'));
        startMarker?.getElement().classList.add('entry-map-endpoint--pending');
        finishMarker?.getElement().classList.add('entry-map-endpoint--pending');
        drawHead?.remove();
        drawHead = null;
    }

    startDraw = () => {
        if (drawStartTimer !== null) {
            clearTimeout(drawStartTimer);
            drawStartTimer = null;
        }

        // A finished draw starts over; a paused one carries on from where it is.
        if (progress >= 1) {
            resetDraw();
        }

        playDraw(progress);
    };

    pauseDraw = () => {
        if (drawStartTimer !== null) {
            clearTimeout(drawStartTimer);
            drawStartTimer = null;
        }

        if (drawFrame !== null) {
            cancelAnimationFrame(drawFrame);
            drawFrame = null;
        }

        playing.value = false;
    };

    /**
     * The dot riding the front of the line as it draws. An out-and-back retraces
     * its own path, so without this the growth is invisible wherever the route
     * overlaps itself.
     */
    function drawHeadElement() {
        const element = document.createElement('div');

        element.dataset.testid = 'draw-head';
        element.className = 'entry-map-head size-3 rounded-full border-2 border-neutral-0';
        element.style.backgroundColor = resolveColor(props.color);
        // Never intercept a click meant for the map or a photo beneath it.
        element.style.pointerEvents = 'none';
        // Below the photo markers (2), same band as the scrub dot.
        element.style.zIndex = '1';

        return element;
    }

    /** Fade the head out where it stopped, then drop it. */
    function retireDrawHead() {
        if (!drawHead) {
            return;
        }

        const retiring = drawHead;

        drawHead = null;
        retiring.getElement().classList.add('entry-map-head--done');
        setTimeout(() => retiring.remove(), 260);
    }

    /** Show every photo whose nearest point on the route has been drawn. */
    function revealPhotosUpTo(count) {
        photoReveals.forEach(({ element, at }) => {
            if (at <= count) {
                element.classList.remove('entry-map-photo--pending');
            }
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

            // Recorded whether or not the intro plays: the replay control needs
            // these to hide the photos again before drawing a second time.
            photoReveals.push({ element, at: nearestCoordIndex(photo) });

            if (animating) {
                element.classList.add('entry-map-photo', 'entry-map-photo--pending');
            }
        });
    }

    /**
     * Where the route starts and where it ends. While the intro plays each is
     * held back until the line reaches it, so they punctuate the draw rather
     * than giving away its shape in advance.
     */
    function addEndpointMarkers() {
        const start = endpointElement(false);
        const finish = endpointElement(true);

        if (animating) {
            start.classList.add('entry-map-endpoint--pending');
            finish.classList.add('entry-map-endpoint--pending');
        }

        startMarker = new maplibregl.Marker({ element: start }).setLngLat(coords[0]).addTo(map);
        finishMarker = new maplibregl.Marker({ element: finish }).setLngLat(coords[coords.length - 1]).addTo(map);
    }

    /** A route endpoint: a plain dot to start, a chequered one to finish. */
    function endpointElement(isFinish) {
        const element = document.createElement('div');

        element.dataset.testid = isFinish ? 'route-finish' : 'route-start';
        element.className = `entry-map-endpoint size-3.5 rounded-full border-2 ${isFinish ? 'border-neutral-0 entry-map-endpoint--finish' : 'entry-map-endpoint--start'}`;

        element.style.pointerEvents = 'none';
        // Below the photo markers (2), above the line itself.
        element.style.zIndex = '1';

        return element;
    }

    /**
     * The index of the route coordinate closest to a photo, which is when the
     * draw should reveal it. Squared distance is enough for a comparison, and
     * a route is a few thousand points against a handful of photos.
     */
    function nearestCoordIndex(photo) {
        let best = 0;
        let bestDistance = Infinity;

        coords.forEach(([lng, lat], index) => {
            const distance = (lng - photo.longitude) ** 2 + (lat - photo.latitude) ** 2;

            if (distance < bestDistance) {
                bestDistance = distance;
                best = index;
            }
        });

        return best;
    }

    // The intro plays once, on first load, and only when there is a route to
    // draw. Someone who asked for less motion gets the finished map instead.
    const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;

    animating = coords.length > 1 && ! reducedMotion;
    drawn = animating ? 0 : coords.length;
    progress = animating ? 0 : 1;
    replayable.value = coords.length > 1;

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
    addEndpointMarkers();

    map.on('error', (event) => console.error('[EntryMap] MapLibre error', event?.error || event));

    map.on('load', () => {
        addRouteLayer();

        if (animating) {
            playing.value = true;
            drawStartTimer = setTimeout(() => playDraw(), DRAW_START_DELAY);
        }
    });

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
    if (drawStartTimer !== null) {
        clearTimeout(drawStartTimer);
        drawStartTimer = null;
    }

    if (drawFrame !== null) {
        cancelAnimationFrame(drawFrame);
        drawFrame = null;
    }

    photoReveals = [];
    drawHead?.remove();
    drawHead = null;
    startMarker?.remove();
    finishMarker?.remove();
    startMarker = null;
    finishMarker = null;
    stopThemeWatch?.();
    markers.forEach((marker) => marker.remove());
    markers = [];
    stopTrackWatch?.();
    stopCursorWatch?.();
    routeDot?.remove();
    routeDot = null;
    startDraw = null;
    pauseDraw = null;
    playing.value = false;
    map?.remove();
    map = null;
});
</script>

<template>
    <div class="relative">
        <div ref="container" class="w-full overflow-hidden rounded-lg border border-neutral-50" :class="heightClass" />
        <div v-if="ready" class="absolute left-2.5 top-2.5 z-10 flex gap-1.5">
            <button
                type="button"
                class="flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                aria-label="Re-center map"
                @click="recenter"
            >
                <Icon name="CenterFocusIcon" class="size-4" />
            </button>

            <button
                v-if="replayable"
                type="button"
                data-testid="replay-route"
                class="flex size-8 items-center justify-center rounded-md border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-sm transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                :aria-label="playing ? 'Pause the route replay' : 'Replay the route'"
                @click="toggleReplay"
            >
                <Icon :name="playing ? 'PauseIcon' : 'PlayIcon'" class="size-4" />
            </button>
        </div>

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
