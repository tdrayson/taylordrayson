<script setup>
import { computed, ref } from 'vue';
import Icon from './Icon.vue';
import { videoEmbed, videoProvider, videoThumbnails } from '../../lib/video';

/**
 * A video in a document, held behind its still until it is asked for.
 *
 * A YouTube or Vimeo iframe pulls several hundred kilobytes of player and talks
 * to the host on sight, so an article with one embed pays for it whether or not
 * anyone watches. Nothing but the thumbnail loads until the reader presses play.
 *
 * @param {string} url The video URL: YouTube, Vimeo, or a direct file.
 * @param {string|null} caption Optional caption, also the embed's accessible name.
 * @param {string|null} poster Overrides the host's own thumbnail.
 */
const props = defineProps({
    url: { type: String, required: true },
    caption: { type: String, default: null },
    poster: { type: String, default: null },
    width: { type: Number, default: null },
    height: { type: Number, default: null },
});

// Whether the reader has asked for it; flips the still for the real frame.
const playing = ref(false);

// Candidates in preference order: an author's own poster, then whatever the host
// publishes. How many have 404'd so far, so a missing thumbnail steps down to
// the next rather than leaving a broken image behind the play button.
const candidates = computed(() => [props.poster, ...videoThumbnails(props.url)].filter(Boolean));
const failed = ref(0);
const still = computed(() => candidates.value[failed.value] ?? null);

// Only a hosted video needs the still. A direct file plays in a <video>, which
// fetches nothing of its own while preload is off.
const provider = computed(() => videoProvider(props.url));

// Autoplays because reaching this URL at all means play was already pressed.
const embed = computed(() => videoEmbed(props.url, { autoplay: true }));

const label = computed(() => (props.caption ? `Play video: ${props.caption}` : 'Play video'));
</script>

<template>
    <figure class="max-w-media">
        <iframe
            v-if="provider && playing"
            :src="embed"
            :title="caption || 'Video'"
            allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture"
            allowfullscreen
            class="block aspect-video w-full rounded-lg border border-neutral-50"
        ></iframe>

        <button
            v-else-if="provider"
            type="button"
            class="group/play relative flex aspect-video w-full items-center justify-center overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
            :aria-label="label"
            @click="playing = true"
        >
            <!-- object-cover because the hqdefault fallback pads a 16:9 still
                 into a 4:3 frame, and the crop takes the letterboxing back off. -->
            <img
                v-if="still"
                :key="still"
                :src="still"
                alt=""
                loading="lazy"
                class="absolute inset-0 size-full object-cover"
                @error="failed++"
            >

            <span
                class="relative flex size-14 items-center justify-center rounded-full bg-accent-500 text-neutral-0 shadow-card transition-colors group-hover/play:bg-accent-700 group-focus-visible/play:bg-accent-700"
            >
                <Icon name="PlayIcon" class="ml-0.5 size-6" />
            </span>

            <!-- Names the host: pressing play is what contacts it, so it is
                 worth saying which one before that happens. -->
            <span
                class="absolute bottom-2 right-2 rounded-md px-2 py-1 text-label uppercase"
                :class="still ? 'bg-black/55 text-white' : 'text-neutral-500'"
            >{{ provider }}</span>
        </button>

        <!-- preload none: a direct file should cost nothing either until asked for. -->
        <video
            v-else
            :src="url"
            :poster="poster || undefined"
            controls
            preload="none"
            :width="width || undefined"
            :height="height || undefined"
            class="block max-h-media w-full rounded-lg border border-neutral-50"
        ></video>

        <figcaption v-if="caption" class="mt-2 text-left text-meta text-neutral-500">{{ caption }}</figcaption>
    </figure>
</template>
