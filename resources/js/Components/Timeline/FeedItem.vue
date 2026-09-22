<script setup>
import { ref, computed, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import ReactionBar from '../Conversation/ReactionBar.vue';
import { useInteractions } from '../../lib/interactionContext.js';
import Button from '../Ui/Button.vue';
import Pill from '../Ui/Pill.vue';
import Tooltip from '../Ui/Tooltip.vue';
import ZoomButton from '../Ui/ZoomButton.vue';
import Heading from '../Ui/Heading.vue';
import StageBar from '../Stats/StageBar.vue';
import FlightRoute from '../Maps/FlightRoute.vue';
import Lightbox from '../Overlays/Lightbox.vue';
import CardMediaCarousel from './CardMediaCarousel.vue';
import NoteBody from '../Ui/NoteBody.vue';
import ResponseContext from '../Entry/ResponseContext.vue';
import { entryType } from '../../entryTypes.js';
import { clock, duration, flightDurationLabel } from '../../lib/format.js';
import { player, playAudio, playVideo, togglePlay, isCurrent, dockVideo, undockVideo } from '../../lib/player.js';
import { useFormat } from '../../composables/useFormat';
import { useTheme } from '../../useTheme';

const props = defineProps({
    // Registry name string (from entryTypes) or a raw hugeicons object.
    icon: { type: [Array, Object, String], default: null },
    iconKey: { type: String, default: null },
    accent: { type: String, default: null },
    type: { type: String, default: '' },
    // The entry's own key, for addressing the reaction endpoint.
    id: { type: [Number, String], default: null },
    time: { type: String, default: '' },
    datetime: { type: String, default: null },
    title: { type: String, required: true },
    // Accessible name for the title when the visible text lacks context (e.g. "3,145 kcal").
    titleLabel: { type: String, default: null },
    // Full note content as a Portable Text document: title-less types render
    // this instead of the display-font title, with the timestamp acting as the
    // permalink. Rendered rather than flattened so its links survive the feed.
    body: { type: [Array, String], default: null },
    meta: { type: String, default: '' },
    // Structured subtitle tokens (raw metres/kg + literal text) composed reactively
    // via useFormat; null falls back to the plain `meta` string (e.g. notes).
    metaTokens: { type: Array, default: null },
    segments: { type: Array, default: null },
    route: { type: Object, default: null },
    media: { type: Object, default: null },
    photos: { type: Array, default: null },
    polyline: { type: String, default: null },
    // Wide artwork for a film or episode (an episode borrows its show's). Shown
    // as context, so unlike `photos` it has no lightbox.
    backdrop: { type: String, default: null },
    // What this post responds to, for a note or article that answers somebody.
    // The same ResponseData the entry page draws, in its compact form.
    response: { type: Object, default: null },
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
    // A check-in's full address, shown beneath the map regardless of whether the
    // card has a note.
    address: { type: String, default: null },
    // Foursquare's own word for what the place is, shown as a label rather than
    // written into a sentence: the vocabulary includes Road, Platform and Town.
    category: { type: String, default: null },
    // Multi-day span ({ start, end, days, label }), e.g. a multi-day event.
    range: { type: Object, default: null },
    pb: { type: Boolean, default: false },
    url: { type: String, default: null },
    label: { type: String, default: '' },
    offset: { type: String, default: '' },
    // Set only for an entry that is not published, which only the owner's search returns.
    statusLabel: { type: String, default: null },
});

// Unit-aware distance formatter; route.distance is already in miles.
const { distance, weight, distanceFromMiles } = useFormat();

// A note renders its document in place of the display-font title, so the
// heading below is a v-else on this rather than on `body` being truthy: an
// empty array is truthy and would silently swallow the title.
const hasBody = computed(() => (Array.isArray(props.body) ? props.body.length > 0 : Boolean(props.body)));

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
    // Each token may carry a `sep` (e.g. ' in ') to join it onto the previous
    // token with a light connective instead of the default ', ' list comma.
    // Empty-text tokens are dropped before joining so a missing value never
    // leaves a dangling separator (e.g. no leading "in" when duration is first).
    const parts = props.metaTokens
        .map((token) => {
            if (token.t === 'dist') {
                return { text: distance(token.m, token.p), sep: token.sep ?? ', ' };
            }
            if (token.t === 'wt') {
                return { text: weight(token.kg, token.p), sep: token.sep ?? ', ' };
            }
            return { text: token.v, sep: token.sep ?? ', ' };
        })
        .filter((part) => part.text);

    return parts.map((part, index) => (index === 0 ? '' : part.sep) + part.text).join('');
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

const typeColor = computed(() => `var(--color-${props.accent ?? props.iconKey})`);

// Stored static map for this entry (activity route, flight arc, event/fuel/place
// pin), pre-generated server-side. Shown only when there is no cover photo.
const routeImageUrl = computed(() => props.map ?? null);

// Dark twin of the stored map; the two <img> swap via dark:hidden / dark:block.
const routeImageDarkUrl = computed(() => props.mapDark ?? null);

const fullTimestamp = computed(() => (props.label ? `${props.label} ${props.offset}`.trim() : props.time));

// Activity photos: the cover sits beside the route map, with a "+N" badge for
// any extras. Clicking any of the card's media opens the lightbox in place
// rather than navigating; the entry is still one click away from the title, the
// timestamp, and the lightbox's own "View entry" link.
const coverPhoto = computed(() => props.photos?.[0] ?? null);
const extraPhotos = computed(() => (props.photos ? props.photos.length - 1 : 0));

const lightboxIndex = ref(null);

// The map trails the photos, matching CardMediaCarousel's slide order, so one
// index addresses both.
const mapLightboxIndex = computed(() => props.photos?.length ?? 0);

// The card renders both map PNGs and lets `dark:` pick one, but the lightbox
// shows a single image, so the active scheme chooses it here instead.
const { resolved } = useTheme();

// Lightbox slides: the photos, then the map. Each carries the entry permalink so
// the lightbox offers the link the image itself used to be.
const lightboxItems = computed(() => {
    const items = (props.photos ?? []).map((photo) => ({ ...photo, url: props.url }));

    if (routeImageUrl.value) {
        items.push({
            full: resolved.value === 'dark' && routeImageDarkUrl.value ? routeImageDarkUrl.value : routeImageUrl.value,
            url: props.url,
        });
    }

    return items;
});

function openLightbox(index) {
    lightboxIndex.value = index;
}

// Deferred, so absent on first paint and present on the second request. A card
// whose type takes no interactions never gets a row at all.
const interactions = useInteractions();
// Keyed on iconKey: that is the timeline type value the reaction endpoint is
// addressed by. The `type` prop is the display label and is empty in the feed.
const row = computed(() => (props.id === null ? null : interactions.value[`${props.iconKey}:${props.id}`] ?? null));
</script>

<template>
    <div class="relative block h-entry" :style="{ '--type-color': typeColor }">
        <span class="absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 text-(--type-color) lg:-left-12">
            <Icon :icon="displayIcon" class="size-5" />
        </span>
        <div class="flex min-h-9 items-center">
            <div class="flex items-baseline gap-2.5">
                <component
                    :is="typeHref ? Link : 'div'"
                    :href="typeHref || undefined"
                    class="p-category text-2xs font-semibold uppercase tracking-wider text-(--type-color)"
                    :class="typeHref ? 'underline-offset-2 hover:underline focus-visible:underline' : ''"
                >{{ displayType }}</component>
                <Tooltip v-if="datetime" :label="fullTimestamp" placement="top">
                    <Link v-if="url" :href="url" :aria-label="fullTimestamp" class="u-url underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline">
                        <time :datetime="datetime" class="dt-published text-xs text-neutral-500 tabular-nums transition-colors hover:text-accent-500">{{ time }}</time>
                    </Link>
                    <time v-else :datetime="datetime" :aria-label="fullTimestamp" class="dt-published text-xs text-neutral-500 tabular-nums">{{ time }}</time>
                </Tooltip>
                <span v-else-if="time" class="text-xs text-neutral-500 tabular-nums">{{ time }}</span>
                <Pill v-if="statusLabel" :label="statusLabel" />
            </div>
        </div>
        <!-- Above the words, the same order the entry page reads in. -->
        <ResponseContext v-if="response" :response="response" class="mt-1.5" />

        <NoteBody v-if="hasBody" :document="body" />
        <!-- A real h3: each card is a subsection of its DateGroup's h2/h3
             heading. A gesture has none, because the line above is the card:
             its title only restates that line in a display face. -->
        <Heading v-else-if="! response?.namedInTitle" as="h3" size="title" class="mt-1 max-w-md">
            <component
                :is="url ? Link : 'span'"
                v-twemoji
                :href="url || undefined"
                :aria-label="titleLabel || undefined"
                class="p-name"
                :class="url ? 'u-url underline-offset-4 transition-colors hover:text-(--type-color) hover:underline focus-visible:text-(--type-color) focus-visible:underline' : ''"
            >{{ title }}</component>
        </Heading>
        <p v-if="category" class="mt-1.5 text-xs text-neutral-500">{{ category }}</p>
        <div v-if="brandLogo || brand" class="mt-1.5 flex items-center gap-1.5 text-xs text-neutral-500">
            <span v-if="brandLogo" class="inline-flex size-6 items-center justify-center overflow-hidden rounded bg-white ring-1 ring-neutral-100">
                <img :src="brandLogo" alt="" class="size-full object-contain p-0.5">
            </span>
            <span v-if="brand">{{ brand }} garage</span>
        </div>
        <div v-if="airline" class="mt-1.5 flex items-center gap-1.5 text-xs text-neutral-500">
            <img v-if="airline.icon" :src="airline.icon" :alt="airline.name" class="size-4 shrink-0 object-contain">
            <span>{{ airline.name }}</span>
            <span v-if="airline.number" class="text-neutral-400 tabular-nums">{{ airline.number }}</span>
        </div>
        <span v-if="range" class="mt-1.5 block text-xs text-neutral-400">{{ range.label }} ({{ range.days }} days)</span>
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
        <p v-else-if="metaText" v-twemoji class="p-summary mt-2 line-clamp-3 max-w-prose text-sm" :class="pb ? 'font-semibold text-accent-500' : 'text-neutral-700'">{{ metaText }}</p>
        <!-- Map alone when there is no photo, and it opens the lightbox like a
             photo would. Light/dark PNGs are both rendered and the `dark:` class
             picks the right one, no JS needed. -->
        <button
            v-if="routeImageUrl && !coverPhoto"
            type="button"
            class="group/zoom relative mt-3 block aspect-video w-full max-w-lg cursor-zoom-in overflow-hidden rounded-lg border border-neutral-50"
            aria-label="View map"
            @click="openLightbox(mapLightboxIndex)"
        >
            <img :src="routeImageUrl" alt="" class="size-full object-cover" :class="routeImageDarkUrl ? 'dark:hidden' : ''">
            <img v-if="routeImageDarkUrl" :src="routeImageDarkUrl" alt="" class="hidden size-full object-cover dark:block">
            <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                <ZoomButton />
            </span>
        </button>

        <!-- Small screens with both a map and photos get a swipeable carousel;
             the lg+ layout below keeps them side by side. -->
        <CardMediaCarousel
            v-if="routeImageUrl && coverPhoto"
            :map="routeImageUrl"
            :map-dark="routeImageDarkUrl"
            :photos="photos"
            class="lg:hidden"
            @open="openLightbox"
        />

        <!-- Context, not a photograph: no zoom button and no lightbox, and the
             whole thing is aria-hidden because the text permalink above says
             the same. Lazy, since media is the bulk of the feed. -->
        <component
            :is="url ? Link : 'div'"
            v-if="backdrop"
            :href="url || undefined"
            :tabindex="url ? -1 : undefined"
            :aria-hidden="url ? 'true' : undefined"
            class="mt-3 block aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50"
        >
            <img :src="backdrop" alt="" loading="lazy" decoding="async" class="size-full object-cover">
        </component>

        <!-- The cover is the lightbox trigger; the zoom chip and the "+N" badge
             ride inside it as decoration, so the whole image is one hit target. -->
        <div
            v-if="coverPhoto && !routeImageUrl"
            class="focus-frame group/zoom relative mt-3 block aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50"
        >
            <button
                type="button"
                class="focus-frame-target block size-full cursor-zoom-in"
                aria-label="View photos"
                @click="openLightbox(0)"
            >
                <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="100vw" alt="" class="size-full object-cover">
            </button>
            <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                <ZoomButton />
            </span>
            <span v-if="extraPhotos > 0" class="pointer-events-none absolute bottom-2 right-2 rounded-md bg-black/70 px-1.5 py-0.5 text-xs font-semibold text-white tabular-nums">+{{ extraPhotos }}</span>
        </div>

        <!-- A wide route map beside a square cover on lg+, sharing one fixed height
             so the row never needs flex height-matching. The square is the fixed
             part and the map takes what is left: sizing the map by its aspect
             instead makes the pair too wide for the content column. -->
        <div
            v-if="routeImageUrl && coverPhoto"
            class="mt-3 hidden gap-2 lg:flex"
        >
            <div class="group/zoom relative min-w-0 max-w-lg flex-1">
                <button
                    type="button"
                    class="block w-full cursor-zoom-in rounded-lg"
                    aria-label="View map"
                    @click="openLightbox(mapLightboxIndex)"
                >
                    <img :src="routeImageUrl" alt="" class="h-72 w-full rounded-lg border border-neutral-50 object-cover" :class="routeImageDarkUrl ? 'dark:hidden' : ''">
                    <img v-if="routeImageDarkUrl" :src="routeImageDarkUrl" alt="" class="hidden h-72 w-full rounded-lg border border-neutral-50 object-cover dark:block">
                </button>
                <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                    <ZoomButton />
                </span>
            </div>
            <div class="group/zoom relative shrink-0">
                <button
                    type="button"
                    class="block cursor-zoom-in rounded-lg"
                    aria-label="View photos"
                    @click="openLightbox(0)"
                >
                    <img :src="coverPhoto.src" :srcset="coverPhoto.srcset || undefined" sizes="320px" alt="" class="aspect-square h-72 w-auto max-w-none rounded-lg border border-neutral-50 object-cover">
                </button>
                <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                    <ZoomButton />
                </span>
                <span v-if="extraPhotos > 0" class="pointer-events-none absolute bottom-2 right-2 rounded-md bg-black/70 px-1.5 py-0.5 text-xs font-semibold text-white tabular-nums">+{{ extraPhotos }}</span>
            </div>
        </div>
        <!-- Check-in's full address, shown beneath the map/photos whether or not
             the card carries a note. -->
        <p v-if="address" class="mt-3 text-xs text-neutral-500">{{ address }}</p>

        <Lightbox v-model:index="lightboxIndex" :photos="lightboxItems" />
        <div
            v-if="media?.thumbnail && media?.videoUrl"
            ref="videoSlot"
            class="focus-frame relative mt-3 aspect-video w-full max-w-lg overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25"
        >
            <button
                v-if="!playingInline"
                type="button"
                class="focus-frame-target group absolute inset-0"
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
                <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
                <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition-colors group-hover:bg-black/30">
                    <span class="flex size-12 items-center justify-center rounded-full bg-neutral-0/90 text-neutral-900 shadow-card transition-transform group-hover:scale-110">
                        <Icon name="PlayIcon" class="size-5" />
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

        <!-- The counts arrive on a second request, so the row fades in rather than
             appearing all at once. -->
        <Transition name="fade">
        <ReactionBar
            v-if="row"
            variant="compact"
            class="mt-3"
            :type="iconKey"
            :id="Number(id)"
            :url="url"
            :reactions="row.reactions"
            :like-count="row.likeCount"
            :reply-count="row.replyCount"
            :repost-count="row.repostCount"
            :bookmark-count="row.bookmarkCount"
            :rsvp-count="row.rsvpCount"
            :mention-count="row.mentionCount"
        />
        </Transition>
    </div>
</template>
