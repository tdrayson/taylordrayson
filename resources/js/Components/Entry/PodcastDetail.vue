<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import Accordion from '../Ui/Accordion.vue';
import { player, playAudio, playVideo, togglePlay, isCurrent, dockVideo, undockVideo } from '../../lib/player.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const slot = ref(null);
const episodeUrl = usePage().url.split('?')[0];

const track = computed(() => ({
    id: props.entry.id,
    title: `Season ${props.entry.season_number}, Episode ${props.entry.episode_number}`,
    audioUrl: props.entry.audio_url,
    videoUrl: props.entry.video_url,
    thumbnail: props.entry.thumbnail,
    url: episodeUrl,
}));

const cover = computed(() => props.entry.cover_image || props.entry.thumbnail);
const durationLabel = computed(() => {
    const total = props.entry.duration;

    if (!total) {
        return null;
    }

    const hours = Math.floor(total / 3600);
    const minutes = Math.floor((total % 3600) / 60);
    const seconds = total % 60;

    return [hours ? `${hours}h` : null, hours || minutes ? `${minutes}m` : null, `${seconds}s`]
        .filter(Boolean)
        .join(' ');
});
const audioPlaying = computed(() => isCurrent(track.value, 'audio') && player.playing);
const playingInline = computed(() => isCurrent(track.value, 'video'));
const showUrl = computed(
    () => `https://www.thisweekwith.co.uk/season-${props.entry.season_number}/episode-${props.entry.episode_number}/`,
);

function listen() {
    if (isCurrent(track.value, 'audio')) {
        togglePlay();
    } else {
        playAudio(track.value);
    }
}

function watchVideo() {
    playVideo(track.value);
    dockVideo(slot.value);
}

onMounted(() => {
    // Returning to this episode while its video plays in the corner re-docks it inline.
    if (isCurrent(track.value, 'video')) {
        dockVideo(slot.value);
    }
});

onBeforeUnmount(() => {
    undockVideo(slot.value);
});
</script>

<template>
    <div class="space-y-8">
        <div
            v-if="entry.video_url"
            ref="slot"
            class="relative aspect-video w-full overflow-hidden rounded-lg border border-line-2 bg-surface"
        >
            <button
                v-if="!playingInline"
                type="button"
                class="group absolute inset-0"
                aria-label="Watch on YouTube"
                @click="watchVideo"
            >
                <img v-if="cover" :src="cover" alt="" class="size-full object-cover transition-transform duration-300 group-hover:scale-105">
                <span class="absolute inset-0 flex items-center justify-center bg-ink/20 transition-colors group-hover:bg-ink/30">
                    <span class="flex size-16 items-center justify-center rounded-full bg-canvas/90 text-ink shadow-card transition-transform group-hover:scale-110">
                        <Icon :icon="PlayIcon" class="size-7" />
                    </span>
                </span>
            </button>
        </div>
        <img v-else-if="cover" :src="cover" alt="" class="aspect-video w-full rounded-lg border border-line-2 object-cover">

        <p v-if="durationLabel" class="text-meta text-ink-3">
            Duration: <span class="tnum text-ink-2">{{ durationLabel }}</span>
        </p>

        <div class="flex flex-wrap items-center gap-4">
            <Button v-if="entry.audio_url" variant="primary" size="lg" pill @click="listen">
                <Icon :icon="audioPlaying ? PauseIcon : PlayIcon" class="size-5" />
                {{ audioPlaying ? 'Pause' : 'Listen' }}
            </Button>
            <ExternalLink :href="showUrl" label="View on This Week With" />
        </div>

        <div v-if="entry.topic">
            <SectionHead title="This Week's Topics" />
            <p class="max-w-prose text-body text-ink-2">{{ entry.topic }}</p>
        </div>

        <Accordion v-if="entry.show_notes" title="Show Notes">
            <div class="show-notes max-w-prose whitespace-pre-line text-body text-ink-2" v-html="entry.show_notes"></div>
        </Accordion>
    </div>
</template>

<style scoped>
.show-notes :deep(a) {
    color: var(--color-accent);
    text-decoration: underline;
    text-underline-offset: 2px;
    overflow-wrap: anywhere;
}

.show-notes :deep(a:hover) {
    color: var(--color-accent-active);
}
</style>
