<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import Tooltip from '../Ui/Tooltip.vue';
import ZoomButton from '../Ui/ZoomButton.vue';
import StageBar from '../Stats/StageBar.vue';
import FlightRoute from '../Maps/FlightRoute.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import { entryType } from '../../entryTypes.js';
import { clock, duration, flightDurationLabel } from '../../lib/format.js';
import { player, playAudio, playVideo, togglePlay, isCurrent, dockVideo, undockVideo } from '../../lib/player.js';
import { useFormat } from '../../composables/useFormat';

const props = defineProps({
    icon: { type: [Array, Object], default: null },
    iconKey: { type: String, default: null },
    accent: { type: String, default: null },
    type: { type: String, default: '' },
    time: { type: String, default: '' },
    datetime: { type: String, default: null },
    title: { type: String, required: true },
    // Accessible name for the title when the visible text lacks context (e.g. "3,145 kcal").
    titleLabel: { type: String, default: null },
    // Full note content: title-less types render this as body text instead of
    // the display-font title, with the timestamp acting as the permalink.
    body: { type: String, default: null },
    meta: { type: String, default: '' },
    // Structured subtitle tokens (raw metres/kg + literal text) composed reactively
    // via useFormat; null falls back to the plain `meta` string (e.g. notes).
    metaTokens: { type: Array, default: null },
    segments: { type: Array, default: null },
    route: { type: Object, default: null },
    media: { type: Object, default: null },
    photos: { type: Array, default: null },
    polyline: { type: String, default: null },
    // A pre-generated static map (e.g. an event's location map), shown in the
    // same banner slot as an activity/flight's live-rendered route map.
    map: { type: String, default: null },
    // Dark-mode twin of `map` (mapbox/dark-v11). Older data without a dark
    // variant simply omits this and the light PNG shows in both themes.
    mapDark: { type: String, default: null },
    // Stored brand logo for fuel entries (e.g. /logos/brands/bp.png); null when
    // the brand has no downloaded logo. Rendered as a small white chip.
    brandLogo: { type: String, default: null },
    // The garage brand name (e.g. "BP"), shown as a text label beside the brand
    // logo chip so the small mark on a fuel card isn't context-less.
    brand: { type: String, default: null },
    // Multi-day span ({ start, end, days, label }), e.g. a multi-day event.
    range: { type: Object, default: null },
    pb: { type: Boolean, default: false },
    url: { type: String, default: null },
    label: { type: String, default: '' },
    offset: { type: String, default: '' },
});

// Unit-aware distance formatter; route.distance is already in miles.
const { distance, weight, distanceFromMiles } = useFormat();

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

// Timeline card subtitle. When the server sends structured tokens, compose them
// through useFormat so distance/weight react to the unit toggle; otherwise fall
// back to the plain server string (e.g. notes have no unit-bearing subtitle).
const metaText = computed(() => {
    if (!props.metaTokens) {
        return props.meta;
    }
    return props.metaTokens
        .map((token) => {
            if (token.t === 'dist') {
                return distance(token.m, token.p);
            }
            if (token.t === 'wt') {
                return weight(token.kg, token.p);
            }
            return token.v;
        })
        .filter(Boolean)
        .join(', ');
});

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
        note: props.route.distance ? distanceFromMiles(props.route.distance) : null,
    };
});

const airline = computed(() => props.route?.airline ?? null);

// The data type's colour token (accent resolves divergent keys, e.g. calorie → food).
const typeColor = computed(() => `var(--color-${props.accent ?? props.iconKey})`);

// Stored static map for this entry (activity route, flight arc, event/fuel/checkin
// pin), pre-generated server-side. Shown only when there is no cover photo.
const routeImageUrl = computed(() => props.map ?? null);

// Dark twin of the stored map; the two <img> swap via dark:hidden / dark:block.
const routeImageDarkUrl = computed(() => props.mapDark ?? null);

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
        <!-- Outer row centres against the icon rail; inner group baseline-aligns the label and time. -->
        <div class="flex min-h-9 items-center">
            <div class="flex items-baseline gap-2.5">
                <component
                    :is="typeHref ? Link : 'div'"
                    :href="typeHref || undefined"
                    class="type-color p-category text-label uppercase"
                    :class="typeHref ? 'underline-offset-2 hover:underline focus-visible:underline' : ''"
                >{{ displayType }}</component>
                <!-- Timestamp is the card's permalink; full date shows as a tooltip and is the link's aria-label. -->
                <Tooltip v-if="datetime" :label="fullTimestamp" placement="top">
                    <Link v-if="url" :href="url" :aria-label="fullTimestamp" class="u-url underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline">
                        <time :datetime="datetime" class="dt-published text-xs text-neutral-500 tnum transition-colors hover:text-accent-500">{{ time }}</time>
                    </Link>
                    <time v-else :datetime="datetime" :aria-label="fullTimestamp" class="dt-published text-xs text-neutral-500 tnum">{{ time }}</time>
                </Tooltip>
                <span v-else-if="time" class="text-xs text-neutral-500 tnum">{{ time }}</span>
            </div>
        </div>
        <!-- Notes show their full content as body text; everything else gets a display-font title. -->
        <p v-if="body" class="e-content mt-1.5 max-w-prose whitespace-pre-line text-base leading-relaxed text-neutral-900">{{ body }}</p>
        <!-- Titles keep a headline measure (~40ch) rather than running full width.
             Each card is a subsection of its DateGroup date heading, so the title
             is a real h3, one level under DateGroup's h2/h3 (see the heading-ladder
             convention: DateGroup h2/h3 -> FeedItem h3). -->
        <h3 v-else class="mt-1 max-w-md font-display text-item-title">
            <component
                :is="url ? Link : 'span'"
                :href="url || undefined"
                :aria-label="titleLabel || undefined"
                class="p-name"
                :class="url ? 'type-link u-url underline-offset-4 transition-colors hover:underline focus-visible:underline' : ''"
            >{{ title }}</component>
        </h3>
        <div v-if="brandLogo || brand" class="mt-1.5 flex items-center gap-1.5 text-caption text-neutral-500">
            <span v-if="brandLogo" class="inline-flex size-6 items-center justify-center overflow-hidden rounded bg-white ring-1 ring-neutral-100">
                <img :src="brandLogo" alt="" class="size-full object-contain p-0.5">
            </span>
            <span v-if="brand">{{ brand }} garage</span>
        </div>
        <div v-if="airline" class="mt-1.5 flex items-center gap-1.5 text-caption text-neutral-500">
            <img v-if="airline.icon" :src="airline.icon" :alt="airline.name" class="size-4 shrink-0 object-contain">
            <span>{{ airline.name }}</span>
            <span v-if="airline.number" class="text-neutral-400 tnum">{{ airline.number }}</span>
        </div>
        <!-- Multi-day badge, e.g. a festival or conference spanning several days. -->
        <span v-if="range" class="mt-1.5 block text-caption text-neutral-400">{{ range.label }} ({{ range.days }} days)</span>
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
        <p v-else-if="metaText" class="p-summary mt-2 line-clamp-3 max-w-prose text-meta" :class="pb ? 'font-semibold text-accent-500' : 'text-neutral-700'">{{ metaText }}</p>
        <!-- Map alone when there is no photo. Light/dark PNGs are both rendered
             and the `dark:` class picks the right one, no JS needed. -->
        <img v-if="routeImageUrl && !coverPhoto" :src="routeImageUrl" alt="" class="mt-3 aspect-video w-full max-w-lg rounded-lg border border-neutral-50 object-cover" :class="routeImageDarkUrl ? 'dark:hidden' : ''">
        <img v-if="routeImageDarkUrl && !coverPhoto" :src="routeImageDarkUrl" alt="" class="mt-3 hidden aspect-video w-full max-w-lg rounded-lg border border-neutral-50 object-cover dark:block">

        <!-- Cover on its own (small screens, or no route map). Image link and zoom button are
             siblings, not nested; the image link duplicates the text permalink so it is aria-hidden. -->
        <div
            v-if="coverPhoto"
            class="group/zoom relative mt-3 block aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50"
            :class="routeImageUrl ? 'lg:hidden' : ''"
        >
            <component
                :is="url ? Link : 'div'"
                :href="url || undefined"
                :tabindex="url ? -1 : undefined"
                :aria-hidden="url ? 'true' : undefined"
                class="block size-full"
            >
                <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="100vw" alt="" class="size-full object-cover">
            </component>
            <button type="button" class="absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="View photos" @click="openLightbox(0)">
                <ZoomButton />
            </button>
            <span v-if="extraPhotos > 0" class="absolute bottom-2 right-2 rounded-md bg-black/70 px-1.5 py-0.5 text-caption font-semibold text-white tnum">+{{ extraPhotos }}</span>
        </div>

        <!-- A wide route map (aspect-video, the same 512x288 as a video thumbnail)
             beside a square cover of the same height, like Strava, on lg+ screens.
             Both are sized by a fixed height plus their aspect, so widths follow
             cleanly without flex height-matching. -->
        <div
            v-if="routeImageUrl && coverPhoto"
            class="mt-3 hidden gap-2 lg:flex"
        >
            <component
                :is="url ? Link : 'div'"
                :href="url || undefined"
                :tabindex="url ? -1 : undefined"
                :aria-hidden="url ? 'true' : undefined"
                class="block"
            >
                <img :src="routeImageUrl" alt="" class="aspect-video h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover" :class="routeImageDarkUrl ? 'dark:hidden' : ''">
                <img v-if="routeImageDarkUrl" :src="routeImageDarkUrl" alt="" class="hidden aspect-video h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover dark:block">
            </component>
            <div class="group/zoom relative">
                <component
                    :is="url ? Link : 'div'"
                    :href="url || undefined"
                    :tabindex="url ? -1 : undefined"
                    :aria-hidden="url ? 'true' : undefined"
                    class="block"
                >
                    <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="320px" alt="" class="aspect-square h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover">
                </component>
                <button type="button" class="absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="View photos" @click="openLightbox(0)">
                    <ZoomButton />
                </button>
                <span v-if="extraPhotos > 0" class="absolute bottom-2 right-2 rounded-md bg-black/70 px-1.5 py-0.5 text-caption font-semibold text-white tnum">+{{ extraPhotos }}</span>
            </div>
        </div>
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
                <!-- Fixed bg-black (not bg-neutral-900): this dims the thumbnail behind
                     the play button in both themes, so it must not invert. -->
                <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition-colors group-hover:bg-black/30">
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
