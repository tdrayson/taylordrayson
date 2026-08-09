<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { player, playAudio, togglePlay, isCurrent } from '../../lib/player.js';

const props = defineProps({
    // Latest episode from the backend: { season, episode, publishedAt, duration, url }.
    episode: { type: Object, default: null },
    show: { type: String, default: 'This Week With Taylor & Gordon' },
    archiveHref: { type: String, default: '/this-week-with' },
});

// The two hosts are constant for this show; bubble gradients distinguish them.
const hosts = [
    { initial: 'G', name: 'Gordon', modifier: 'podcast__host--gordon', image: 'https://cdn.shortpixel.ai/spai3/q_lossy+ret_img+to_auto/www.thisweekwith.co.uk/wp-content/uploads/gordon-drayson-optimised-1024x1024-1.png' },
    { initial: 'T', name: 'Taylor', modifier: 'podcast__host--taylor', image: 'https://cdn.shortpixel.ai/spai3/q_lossy+ret_img+to_auto/www.thisweekwith.co.uk/wp-content/uploads/Taylor-headshot.png' },
];

const seasonEpisode = computed(() => {
    if (!props.episode) {
        return null;
    }
    return `S${props.episode.season}, E${props.episode.episode}`;
});

function relativeDate(iso) {
    const published = new Date(iso);
    const now = new Date();
    const days = Math.floor((now - published) / 86400000);

    if (days <= 0) {
        return 'Today';
    }
    if (days === 1) {
        return 'Yesterday';
    }
    if (days < 7) {
        return `${days} days ago`;
    }
    if (days < 28) {
        const weeks = Math.round(days / 7);
        return weeks === 1 ? '1 week ago' : `${weeks} weeks ago`;
    }
    return published.toLocaleDateString('en-GB', { day: 'numeric', month: 'short' });
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
    <div class="podcast rounded-3xl">
        <div class="podcast__top">
            <Link v-if="seasonEpisode" :href="episode.url" class="podcast__episode">{{ seasonEpisode }}</Link>
            <span v-else class="podcast__episode podcast__episode--empty">No episodes yet</span>
            <Link :href="archiveHref" class="podcast__show">{{ show }}</Link>
            <div v-if="metaLine" class="podcast__meta">{{ metaLine }}</div>
        </div>

        <div class="podcast__hosts">
            <div v-for="host in hosts" :key="host.initial" class="podcast__host" :class="host.modifier">
                <span class="podcast__host-bubble" />
                <span class="podcast__host-initial">{{ host.initial }}</span>
                <img class="podcast__host-image" :src="host.image" :alt="host.name" referrerpolicy="no-referrer" @error="onImageError" />
            </div>
        </div>

        <button
            v-if="hasAudio"
            type="button"
            class="podcast__play"
            :class="{ 'podcast__play--active': isPlaying }"
            :aria-label="isPlaying ? 'Pause latest episode' : 'Play latest episode'"
            @click="listen"
        >
            <svg v-if="isPlaying" class="podcast__play-icon podcast__play-icon--pause" width="12" height="15" viewBox="0 0 12 15" fill="none" aria-hidden="true">
                <rect x="1" y="1" width="3.4" height="13" rx="1" fill="#fff" />
                <rect x="7.6" y="1" width="3.4" height="13" rx="1" fill="#fff" />
            </svg>
            <svg v-else class="podcast__play-icon" width="13" height="15" viewBox="0 0 13 15" fill="none" aria-hidden="true">
                <path d="M1 1.3v12.4a1 1 0 0 0 1.5.87l10.2-6.2a1 1 0 0 0 0-1.74L2.5.43A1 1 0 0 0 1 1.3Z" fill="#fff" />
            </svg>
        </button>
    </div>
</template>

<style scoped>
/* The card is the query container; everything sizes in cqw (1cqw ≈ reference
   px ÷ 2.24) so the composition scales with the grid cell. */
.podcast {
    container-type: inline-size;
    position: relative;
    overflow: hidden;
    aspect-ratio: 1 / 1;
    background: var(--color-neutral-0);
    box-shadow: var(--shadow-card);
    color: var(--color-neutral-900);
}

.podcast__top {
    position: absolute;
    left: 8.04cqw;
    top: 8.04cqw;
    right: 7.14cqw;
    z-index: 3;
}

.podcast__episode {
    display: inline-block;
    font-size: 14.29cqw;
    font-weight: 800;
    letter-spacing: -0.02em;
    line-height: 1;
    color: var(--color-neutral-900);
}

.podcast__episode--empty {
    font-size: 8cqw;
    color: var(--color-neutral-500);
}

.podcast__episode:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: 2px;
}

.podcast__show {
    display: -webkit-box;
    margin-top: 4.02cqw;
    font-size: 6.25cqw;
    font-weight: 700;
    line-height: 1.2;
    color: var(--color-neutral-800);
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 2;
    overflow: hidden;
}

.podcast__show:focus-visible {
    outline: 2px solid var(--color-accent-500);
    outline-offset: 2px;
}

.podcast__meta {
    margin-top: 3.13cqw;
    font-size: 5.36cqw;
    font-weight: 600;
    color: var(--color-neutral-500);
}

.podcast__hosts {
    position: absolute;
    left: 3.57cqw;
    bottom: -1.79cqw;
    z-index: 2;
    display: flex;
    align-items: flex-end;
}

.podcast__host {
    position: relative;
    width: 39.29cqw;
    height: 47.32cqw;
}

.podcast__host + .podcast__host {
    margin-left: -17.86cqw;
}

.podcast__host-bubble {
    position: absolute;
    left: 50%;
    bottom: 3.57cqw;
    transform: translateX(-50%);
    width: 35.71cqw;
    height: 35.71cqw;
    border-radius: 50%;
    z-index: 1;
}

.podcast__host--taylor .podcast__host-bubble {
    background: linear-gradient(150deg, #8fdcd7, #75d3cd);
}

.podcast__host--gordon .podcast__host-bubble {
    background: linear-gradient(150deg, #fe7a74, #fd5a53);
}

.podcast__host-initial {
    position: absolute;
    left: 50%;
    bottom: 16.07cqw;
    transform: translateX(-50%);
    z-index: 2;
    font-size: 9.38cqw;
    font-weight: 800;
    color: var(--color-neutral-900);
}

/* Headshots sit above both the bubble and the fallback initial. */
.podcast__host-image {
    position: absolute;
    left: 0;
    bottom: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    object-position: bottom center;
    filter: drop-shadow(0 2.68cqw 4.02cqw rgba(20, 22, 30, 0.22));
    z-index: 3;
}

.podcast__play {
    position: absolute;
    right: 7.14cqw;
    bottom: 7.14cqw;
    z-index: 4;
    display: flex;
    align-items: center;
    justify-content: center;
    width: 16.96cqw;
    height: 16.96cqw;
    padding: 0;
    border: none;
    border-radius: 50%;
    background: var(--color-podcast);
    box-shadow: 0 2.68cqw 6.25cqw color-mix(in srgb, var(--color-podcast) 40%, transparent);
    cursor: pointer;
    appearance: none;
    transition: transform 0.15s ease;
}

.podcast__play:hover {
    transform: scale(1.08);
}

.podcast__play-icon {
    width: 5.8cqw;
    height: auto;
    margin-left: 0.89cqw;
}

.podcast__play-icon--pause {
    margin-left: 0;
}
</style>
