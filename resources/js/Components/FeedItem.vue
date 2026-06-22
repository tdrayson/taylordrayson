<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import StageBar from './StageBar.vue';
import FlightRoute from './FlightRoute.vue';
import { entryType } from '../entryTypes.js';
import { clock, flightDurationLabel, number } from '../format.js';
import { player, playAudio, togglePlay, isCurrent } from '../player.js';

const props = defineProps({
    icon: { type: [Array, Object], default: null },
    iconKey: { type: String, default: null },
    type: { type: String, default: '' },
    time: { type: String, default: '' },
    datetime: { type: String, default: null },
    title: { type: String, required: true },
    meta: { type: String, default: '' },
    segments: { type: Array, default: null },
    route: { type: Object, default: null },
    media: { type: Object, default: null },
    pb: { type: Boolean, default: false },
    url: { type: String, default: null },
});

const mediaPlaying = computed(() => props.media && isCurrent(props.media, 'audio') && player.playing);

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
        duration: flightDurationLabel(props.route.distance),
        note: props.route.distance ? `${number(props.route.distance)} mi` : null,
    };
});

const fullTimestamp = computed(() => {
    if (!props.datetime) {
        return null;
    }

    const date = new Date(props.datetime);

    if (Number.isNaN(date.getTime())) {
        return null;
    }

    const day = date.toLocaleDateString('en-GB', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    });

    if (!props.time.includes(':')) {
        return day;
    }

    return `${day}, ${clock(date)}`;
});
</script>

<template>
    <div class="relative block h-entry">
        <span class="absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-surface text-ink-2">
            <Icon :icon="displayIcon" class="size-5" />
        </span>
        <time v-if="datetime" :datetime="datetime" :title="fullTimestamp" class="dt-published float-right text-xs text-ink-3 tnum">{{ time }}</time>
        <span v-else-if="time" class="float-right text-xs text-ink-3 tnum">{{ time }}</span>
        <component
            :is="typeHref ? Link : 'div'"
            :href="typeHref || undefined"
            class="p-category text-label uppercase text-ink-3"
            :class="typeHref ? 'transition-colors hover:text-accent' : ''"
        >{{ displayType }}</component>
        <div class="mt-1 font-display text-item-title">
            <component
                :is="url ? Link : 'span'"
                :href="url || undefined"
                class="p-name"
                :class="url ? 'u-url transition-colors hover:text-accent' : ''"
            >{{ title }}</component>
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
        <div v-else-if="meta" class="p-summary mt-2 text-meta" :class="pb ? 'font-semibold text-accent' : 'text-ink-2'">{{ meta }}</div>
        <button
            v-if="media?.audioUrl"
            type="button"
            class="mt-3 inline-flex items-center gap-1.5 rounded-full bg-surface px-3 py-1.5 text-label uppercase text-ink-2 transition-colors hover:bg-accent-soft hover:text-accent-active"
            @click="listen"
        >
            <Icon :icon="mediaPlaying ? PauseIcon : PlayIcon" class="size-3.5" />
            {{ mediaPlaying ? 'Pause' : 'Listen' }}
        </button>
        <StageBar v-if="segments?.length" :segments="segments" class="mt-3 max-w-md" />
    </div>
</template>
