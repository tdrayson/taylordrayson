<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { PlayIcon, PauseIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import DetailList from '../Ui/DetailList.vue';
import SectionHead from '../Ui/SectionHead.vue';
import ExternalLink from '../Ui/ExternalLink.vue';
import { duration, titleCase } from '../../lib/format.js';
import { youtubeId } from '../../lib/youtube.js';
import { player, playAudio, playVideo, togglePlay, isCurrent, dockVideo, undockVideo } from '../../lib/player.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const slot = ref(null);
const entryUrl = usePage().url.split('?')[0];

const thumbnail = computed(() => {
    if (props.entry.thumbnail) {
        return props.entry.thumbnail;
    }

    const id = youtubeId(props.entry.video_url);

    return id ? `https://i.ytimg.com/vi/${id}/maxresdefault.jpg` : null;
});

const srcset = computed(() => props.entry.thumbnailSrcset || undefined);

const track = computed(() => ({
    id: `appearance-${props.entry.id}`,
    title: props.entry.title,
    audioUrl: props.entry.audio_url,
    videoUrl: props.entry.video_url,
    thumbnail: thumbnail.value,
    url: entryUrl,
}));

const rows = computed(() => [
    { label: 'Type', value: titleCase(props.entry.type) },
    { label: 'Show', value: props.entry.show_name },
    { label: 'Duration', value: duration(props.entry.duration) },
]);

const audioPlaying = computed(() => isCurrent(track.value, 'audio') && player.playing);
const playingInline = computed(() => isCurrent(track.value, 'video'));

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
    // Returning to this appearance while its video plays in the corner re-docks it inline.
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
            class="relative aspect-video w-full overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25"
        >
            <button
                v-if="!playingInline"
                type="button"
                class="group absolute inset-0 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                aria-label="Watch video"
                @click="watchVideo"
            >
                <img v-if="thumbnail" :src="thumbnail" :srcset="srcset" sizes="(min-width: 768px) 640px, 100vw" alt="" class="size-full object-cover transition-transform duration-300 group-hover:scale-105">
                <!-- Fixed bg-black (not bg-neutral-900): this dims the thumbnail behind
                     the play button in both themes, so it must not invert. -->
                <span class="absolute inset-0 flex items-center justify-center bg-black/20 transition-colors group-hover:bg-black/30">
                    <span class="flex size-16 items-center justify-center rounded-full bg-neutral-0/90 text-neutral-900 shadow-card transition-transform group-hover:scale-110">
                        <Icon :icon="PlayIcon" class="size-7" />
                    </span>
                </span>
            </button>
        </div>
        <img v-else-if="thumbnail" :src="thumbnail" :srcset="srcset" sizes="(min-width: 768px) 640px, 100vw" alt="" class="aspect-video w-full rounded-lg border border-neutral-50 object-cover">

        <DetailList :rows="rows" />

        <div v-if="entry.audio_url" class="flex flex-wrap items-center gap-4">
            <Button variant="primary" size="lg" pill @click="listen">
                <Icon :icon="audioPlaying ? PauseIcon : PlayIcon" class="size-5" />
                {{ audioPlaying ? 'Pause' : 'Listen' }}
            </Button>
        </div>

        <div v-if="entry.description">
            <SectionHead title="About" />
            <p v-twemoji class="max-w-prose whitespace-pre-line text-body text-neutral-700">{{ entry.description }}</p>
        </div>

        <div v-if="entry.url || entry.video_url" class="flex flex-wrap gap-x-6 gap-y-3">
            <ExternalLink v-if="entry.url" :href="entry.url" label="Show page" />
            <ExternalLink v-if="entry.video_url" :href="entry.video_url" label="Watch on YouTube" />
        </div>
    </div>
</template>
