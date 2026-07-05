<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import ZoomButton from '../Ui/ZoomButton.vue';
import StageBar from '../Stats/StageBar.vue';
import FlightRoute from '../Maps/FlightRoute.vue';
import RouteThumb from '../Maps/RouteThumb.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import { entryType } from '../../entryTypes.js';
import { clock, duration, flightDurationLabel, number } from '../../lib/format.js';
import { greatCircle } from '../../lib/maplibre.js';
import { decodePolyline } from '../../lib/geo.js';
import { player, playAudio, playVideo, togglePlay, isCurrent, dockVideo, undockVideo } from '../../lib/player.js';
import { staticRouteMap, staticArcMap } from '../../lib/staticMap.js';

const props = defineProps({
    icon: { type: [Array, Object], default: null },
    iconKey: { type: String, default: null },
    accent: { type: String, default: null },
    type: { type: String, default: '' },
    time: { type: String, default: '' },
    datetime: { type: String, default: null },
    title: { type: String, required: true },
    // Full note content: title-less types render this as body text instead of
    // the display-font title, with the timestamp acting as the permalink.
    body: { type: String, default: null },
    meta: { type: String, default: '' },
    segments: { type: Array, default: null },
    route: { type: Object, default: null },
    media: { type: Object, default: null },
    photos: { type: Array, default: null },
    polyline: { type: String, default: null },
    pb: { type: Boolean, default: false },
    url: { type: String, default: null },
    label: { type: String, default: '' },
    offset: { type: String, default: '' },
});

const videoSlot = ref(null);

// This card is currently showing the video docked inline (vs popped to the corner).
const playingInline = computed(() => isCurrent(props.media, 'video') && player.dockEl === videoSlot.value);
const videoPlaying = computed(() => props.media && isCurrent(props.media, 'video') && player.playing);
const mediaPlaying = computed(() => props.media && isCurrent(props.media, 'audio') && player.playing);

// Play the video docked inside this card. It only pops to the corner when the
// page unmounts; returning to the feed does NOT re-dock (there is no onMounted
// hook), so once popped out it stays in the corner.
function playInline() {
    if (!props.media) {
        return;
    }

    if (!isCurrent(props.media, 'video')) {
        playVideo(props.media);
    }

    dockVideo(videoSlot.value);
}

// A video with no inline thumbnail has nowhere to dock, so it plays straight in
// the corner mini-player.
function watch() {
    if (!props.media) {
        return;
    }

    if (isCurrent(props.media, 'video')) {
        togglePlay();
    } else {
        playVideo(props.media);
    }
}

function listen() {
    if (!props.media) {
        return;
    }

    if (isCurrent(props.media, 'audio')) {
        togglePlay();
    } else {
        playAudio(props.media);
    }
}

// Leaving the page releases the inline dock, popping the video to the corner.
onBeforeUnmount(() => undockVideo(videoSlot.value));

const displayIcon = computed(() => props.icon ?? entryType(props.iconKey).icon);
const displayType = computed(() => props.type || entryType(props.iconKey).label);
const typeHref = computed(() => entryType(props.iconKey).href ?? null);

const clockOf = (value) => {
    if (!value) {
        return null;
    }

    const date = new Date(value);

    return Number.isNaN(date.getTime()) ? null : clock(date);
};

const routeView = computed(() => {
    if (!props.route) {
        return null;
    }

    return {
        origin: props.route.origin,
        destination: props.route.destination,
        departTime: clockOf(props.route.depart),
        arriveTime: clockOf(props.route.arrive),
        duration: props.route.duration ? duration(props.route.duration) : flightDurationLabel(props.route.distance),
        note: props.route.distance ? `${number(props.route.distance)} mi` : null,
    };
});

const airline = computed(() => props.route?.airline ?? null);

// The data type's colour token (accent resolves divergent keys, e.g. calorie → food).
const typeColor = computed(() => `var(--color-${props.accent ?? props.iconKey})`);
const bannerColor = typeColor;

// A full-width map banner: the flight's great-circle arc, or an activity's route.
const flightArc = computed(() => {
    const origin = props.route?.origin;
    const destination = props.route?.destination;

    if (origin?.lat == null || destination?.lat == null) {
        return null;
    }

    return greatCircle(
        { lat: Number(origin.lat), lng: Number(origin.lng) },
        { lat: Number(destination.lat), lng: Number(destination.lng) },
        64,
    );
});

const routePath = computed(() => {
    const points = props.polyline ? decodePolyline(props.polyline) : [];

    return points.length > 1 ? points : null;
});

// Generated static map image: an activity's GPS trace, or a flight's great-circle
// arc. INTERIM: rendered live from Mapbox; will move to a stored
// (Cloudflare-hosted) URL. See lib/staticMap.js.
const routeImageUrl = computed(() => {
    if (props.polyline) {
        return staticRouteMap(props.polyline);
    }

    const origin = props.route?.origin;
    const destination = props.route?.destination;

    if (origin?.lat != null && destination?.lat != null) {
        return staticArcMap(origin, destination);
    }

    return null;
});

const banner = computed(() => {
    if (flightArc.value) {
        return { points: flightArc.value, endpoints: true };
    }

    if (routePath.value) {
        return { points: routePath.value, endpoints: false };
    }

    return null;
});

const fullTimestamp = computed(() => (props.label ? `${props.label} ${props.offset}`.trim() : props.time));

// Activity photos: the cover sits beside the route map, with a "+N" badge for
// any extras. A hover zoom icon opens the photos in a lightbox in place; the map
// is not lightboxed (clicking the card opens the entry's interactive map).
const coverPhoto = computed(() => props.photos?.[0] ?? null);
const extraPhotos = computed(() => (props.photos ? props.photos.length - 1 : 0));

const lightboxIndex = ref(null);
const lightboxItems = computed(() => props.photos ?? []);

function openLightbox(index) {
    lightboxIndex.value = index;
}
</script>

<template>
    <div class="relative block h-entry" :style="{ '--type-color': typeColor }">
        <span class="type-color absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 lg:-left-12">
            <Icon :icon="displayIcon" class="size-5" />
        </span>
        <div class="flex min-h-9 items-center gap-2.5">
            <component
                :is="typeHref ? Link : 'div'"
                :href="typeHref || undefined"
                class="type-color p-category text-label uppercase"
                :class="typeHref ? 'underline-offset-2 hover:underline focus-visible:underline' : ''"
            >{{ displayType }}</component>
            <!-- The timestamp is every card's permalink, like classic microblogs;
                 like every card link it underlines on hover/focus. -->
            <Link v-if="url && datetime" :href="url" class="u-url underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline">
                <time :datetime="datetime" :title="fullTimestamp" class="dt-published text-xs text-neutral-500 tnum transition-colors hover:text-accent-500">{{ time }}</time>
            </Link>
            <time v-else-if="datetime" :datetime="datetime" :title="fullTimestamp" class="dt-published text-xs text-neutral-500 tnum">{{ time }}</time>
            <span v-else-if="time" class="text-xs text-neutral-500 tnum">{{ time }}</span>
        </div>
        <!-- Notes show their full content as body text; everything else gets a display-font title. -->
        <p v-if="body" class="e-content mt-1.5 max-w-reading whitespace-pre-line text-base leading-relaxed text-neutral-900">{{ body }}</p>
        <!-- Titles keep a headline measure (~40ch) rather than running full width. -->
        <div v-else class="mt-1 max-w-md font-display text-item-title">
            <component
                :is="url ? Link : 'span'"
                :href="url || undefined"
                class="p-name"
                :class="url ? 'type-link u-url underline-offset-4 transition-colors hover:underline focus-visible:underline' : ''"
            >{{ title }}</component>
        </div>
        <div v-if="airline" class="mt-1.5 flex items-center gap-1.5 text-caption text-neutral-500">
            <img v-if="airline.icon" :src="airline.icon" :alt="airline.name" class="size-4 shrink-0 object-contain">
            <span>{{ airline.name }}</span>
            <span v-if="airline.number" class="text-neutral-400 tnum">· {{ airline.number }}</span>
        </div>
        <FlightRoute
            v-if="routeView"
            compact
            :origin="routeView.origin"
            :destination="routeView.destination"
            :depart-time="routeView.departTime"
            :arrive-time="routeView.arriveTime"
            :duration="routeView.duration"
            :note="routeView.note"
            class="mt-3 max-w-sm"
        />
        <div v-else-if="meta" class="p-summary mt-2 line-clamp-3 max-w-reading text-meta" :class="pb ? 'font-semibold text-accent-500' : 'text-neutral-700'">{{ meta }}</div>
        <!-- SVG banner only as a fallback when no generated image is available. -->
        <RouteThumb v-if="banner && !routeImageUrl" :points="banner.points" :color="bannerColor" :endpoints="banner.endpoints" class="mt-3" />
        <!-- Map alone when there is no photo. -->
        <img v-if="routeImageUrl && !coverPhoto" :src="routeImageUrl" alt="" class="mt-3 aspect-video w-full max-w-lg rounded-lg border border-neutral-50 object-cover">

        <!-- The cover shown on its own: on small screens (to avoid cramming both),
             and whenever the activity has no route map. -->
        <component
            v-if="coverPhoto"
            :is="url ? Link : 'div'"
            :href="url || undefined"
            class="group/zoom relative mt-3 block aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50"
            :class="routeImageUrl ? 'lg:hidden' : ''"
        >
            <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="100vw" alt="" class="size-full object-cover">
            <button type="button" class="absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="View photos" @click.prevent.stop="openLightbox(0)">
                <ZoomButton />
            </button>
            <span v-if="extraPhotos > 0" class="absolute bottom-2 right-2 rounded-md bg-neutral-900/70 px-1.5 py-0.5 text-caption font-semibold text-neutral-0 tnum">+{{ extraPhotos }}</span>
        </component>

        <!-- A wide route map (aspect-video, the same 512x288 as a video thumbnail)
             beside a square cover of the same height, like Strava, on lg+ screens.
             Both are sized by a fixed height plus their aspect, so widths follow
             cleanly without flex height-matching. -->
        <component
            v-if="routeImageUrl && coverPhoto"
            :is="url ? Link : 'div'"
            :href="url || undefined"
            class="mt-3 hidden gap-2 lg:flex"
        >
            <img :src="routeImageUrl" alt="" class="aspect-video h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover">
            <div class="group/zoom relative">
                <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="320px" alt="" class="aspect-square h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover">
                <button type="button" class="absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="View photos" @click.prevent.stop="openLightbox(0)">
                    <ZoomButton />
                </button>
                <span v-if="extraPhotos > 0" class="absolute bottom-2 right-2 rounded-md bg-neutral-900/70 px-1.5 py-0.5 text-caption font-semibold text-neutral-0 tnum">+{{ extraPhotos }}</span>
            </div>
        </component>
        <Lightbox v-model:index="lightboxIndex" :photos="lightboxItems" />
        <div
            v-if="media?.thumbnail && media?.videoUrl"
            ref="videoSlot"
            class="relative mt-3 aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25"
        >
            <button
                v-if="!playingInline"
                type="button"
                class="group absolute inset-0"
                aria-label="Watch video"
                @click="playInline"
            >
                <img
                    :src="media.thumbnail"
                    :srcset="media.srcset || undefined"
                    sizes="(min-width: 768px) 512px, 100vw"
                    alt=""
                    class="size-full object-cover transition-transform duration-300 group-hover:scale-105"
                >
                <span class="absolute inset-0 flex items-center justify-center bg-neutral-900/20 transition-colors group-hover:bg-neutral-900/30">
                    <span class="flex size-12 items-center justify-center rounded-full bg-neutral-0/90 text-neutral-900 shadow-card transition-transform group-hover:scale-110">
                        <Icon :icon="PlayIcon" class="size-5" />
                    </span>
                </span>
            </button>
        </div>
        <Button
            v-if="media?.videoUrl && !media?.thumbnail"
            variant="chip"
            size="sm"
            pill
            class="mt-3"
            @click="watch"
        >
            <Icon :icon="videoPlaying ? PauseIcon : PlayIcon" class="size-3.5" />
            {{ videoPlaying ? 'Pause' : 'Watch' }}
        </Button>
        <Button
            v-if="media?.audioUrl && !media?.videoUrl"
            variant="chip"
            size="sm"
            pill
            class="mt-3"
            @click="listen"
        >
            <Icon :icon="mediaPlaying ? PauseIcon : PlayIcon" class="size-3.5" />
            {{ mediaPlaying ? 'Pause' : 'Listen' }}
        </Button>
        <StageBar v-if="segments?.length" :segments="segments" class="mt-3 max-w-md" />
    </div>
</template>

<style scoped>
.type-color {
    color: var(--type-color);
}

.type-link:hover,
.type-link:focus-visible {
    color: var(--type-color);
}
</style>
