<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { player, playAudio, togglePlay, isCurrent } from '../../lib/player.js';
import { relativeDay } from '../../lib/format.js';

const props = defineProps({
    // Latest episode from the backend: { season, episode, publishedAt, duration, url }.
    episode: { type: Object, default: null },
    show: { type: String, default: 'This Week With Taylor & Gordon' },
    archiveHref: { type: String, default: '/this-week-with' },
});

// The two hosts are constant for this show; bubble gradients distinguish them.
const hosts = [
    { initial: 'G', name: 'Gordon', position: 'left-0', bubble: 'from-host-gordon-light to-host-gordon', image: 'https://cdn.shortpixel.ai/spai3/q_lossy+ret_img+to_auto/www.thisweekwith.co.uk/wp-content/uploads/gordon-drayson-optimised-1024x1024-1.png' },
    { initial: 'T', name: 'Taylor', position: 'right-0', bubble: 'from-host-taylor-light to-host-taylor', image: 'https://cdn.shortpixel.ai/spai3/q_lossy+ret_img+to_auto/www.thisweekwith.co.uk/wp-content/uploads/Taylor-headshot.png' },
];

const seasonEpisode = computed(() => {
    if (!props.episode) {
        return null;
    }
    return `S${props.episode.season}, E${props.episode.episode}`;
});

// Compact absolute fallback ("3 May") once the shared relative window lapses.
function relativeDate(iso) {
    return relativeDay(iso) ?? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
}

function durationLabel(seconds) {
    if (seconds === null || seconds === undefined) {
        return null;
    }
    const minutes = Math.round(seconds / 60);
    if (minutes < 60) {
        return `${minutes} min`;
    }
    const hours = Math.floor(minutes / 60);
    const remainder = minutes % 60;
    return `${hours}h ${remainder}m`;
}

const metaLine = computed(() => {
    if (!props.episode) {
        return null;
    }
    return [relativeDate(props.episode.publishedAt), durationLabel(props.episode.duration)].filter(Boolean).join(', ');
});

const track = computed(() => props.episode?.media ?? null);
const hasAudio = computed(() => Boolean(track.value?.audioUrl));
const isPlaying = computed(() => hasAudio.value && isCurrent(track.value, 'audio') && player.playing);

function listen() {
    if (!hasAudio.value) {
        return;
    }
    if (isCurrent(track.value, 'audio')) {
        togglePlay();
    } else {
        playAudio(track.value);
    }
}

const onImageError = (event) => {
    event.target.style.display = 'none';
};
</script>

<template>
    <div class="@container relative aspect-square overflow-hidden rounded-3xl bg-neutral-0 text-neutral-900 shadow-card">
        <div class="absolute top-2/25 right-1/14 left-2/25 z-3">
            <Link
                v-if="seasonEpisode"
                :href="episode.url"
                class="inline-block text-2xl leading-none font-extrabold tracking-tight text-neutral-900 @5xs:text-3xl @4xs:text-4xl @xs:text-5xl"
            >
                {{ seasonEpisode }}
            </Link>
            <span v-else class="inline-block text-sm leading-none font-extrabold tracking-tight text-neutral-500 @5xs:text-base @4xs:text-lg @xs:text-2xl">No episodes yet</span>
            <Link
                :href="archiveHref"
                class="mt-1.5 line-clamp-2 text-2xs leading-tight font-bold text-neutral-800 @5xs:mt-2 @5xs:text-xs @4xs:mt-2.5 @4xs:text-sm @xs:mt-3 @xs:text-xl"
            >
                {{ show }}
            </Link>
            <div v-if="metaLine" class="mt-1 text-3xs font-semibold text-neutral-500 @5xs:mt-1.5 @5xs:text-2xs @4xs:mt-2 @4xs:text-xs @xs:mt-2.5 @xs:text-lg">{{ metaLine }}</div>
        </div>

        <div class="absolute -bottom-1/56 left-1/28 z-2 h-53/112 w-17/28">
            <div v-for="host in hosts" :key="host.initial" class="absolute bottom-0 h-full w-11/17" :class="host.position">
                <span class="absolute bottom-3/40 left-1/2 z-1 aspect-square w-10/11 -translate-x-1/2 rounded-full bg-linear-150" :class="host.bubble" />
                <span class="absolute bottom-17/50 left-1/2 z-2 -translate-x-1/2 text-base font-extrabold text-neutral-900 @5xs:text-lg @4xs:text-2xl @xs:text-3xl">
                    {{ host.initial }}
                </span>
                <img
                    class="absolute inset-0 z-3 size-full object-contain object-bottom drop-shadow-lg drop-shadow-black/20"
                    :src="host.image"
                    :alt="host.name"
                    referrerpolicy="no-referrer"
                    @error="onImageError"
                />
            </div>
        </div>

        <button
            v-if="hasAudio"
            type="button"
            class="absolute right-1/14 bottom-1/14 z-4 flex size-17/100 cursor-pointer appearance-none items-center justify-center rounded-full bg-this-week-with p-0 text-white shadow-lg shadow-this-week-with/40 transition-transform duration-150 motion-safe:hover:scale-108"
            :aria-label="isPlaying ? 'Pause latest episode' : 'Play latest episode'"
            @click="listen"
        >
            <svg v-if="isPlaying" class="h-auto w-1/3" width="12" height="15" viewBox="0 0 12 15" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="3.4" height="13" rx="1" fill="currentColor" />
                <rect x="7.6" y="1" width="3.4" height="13" rx="1" fill="currentColor" />
            </svg>
            <svg v-else class="h-auto w-1/3 translate-x-3/20" width="13" height="15" viewBox="0 0 13 15" fill="none" aria-hidden="true">
                <path d="M1 1.3v12.4a1 1 0 0 0 1.5.87l10.2-6.2a1 1 0 0 0 0-1.74L2.5.43A1 1 0 0 0 1 1.3Z" fill="currentColor" />
            </svg>
        </button>
    </div>
</template>
