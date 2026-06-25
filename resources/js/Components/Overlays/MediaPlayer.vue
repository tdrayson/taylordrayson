<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, nextTick } from 'vue';
import { Link } from '@inertiajs/vue3';
import Plyr from 'plyr';
import 'plyr/dist/plyr.css';
import { PlayIcon, PauseIcon, Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import { player, togglePlay, closePlayer } from '../../lib/player.js';
import { videoSource } from '../../lib/video.js';

const audioEl = ref(null);
const plyrTarget = ref(null);
const videoWrap = ref(null);
const currentTime = ref(0);
const duration = ref(0);
const geom = ref({});

let plyr = null;

const isAudio = computed(() => player.mode === 'audio' && player.track);
const isVideo = computed(() => player.mode === 'video' && player.track);
const progress = computed(() => (duration.value ? (currentTime.value / duration.value) * 100 : 0));

// ---- video geometry --------------------------------------------------------
// Docked inline: position ABSOLUTE in document coords, so it scrolls with the
// page naturally (no scroll listener). Undocked: a fixed corner mini-player.
function applyInline() {
    const el = player.dockEl;

    if (!el) {
        return;
    }

    const rect = el.getBoundingClientRect();

    geom.value = {
        position: 'absolute',
        top: `${rect.top + window.scrollY}px`,
        left: `${rect.left + window.scrollX}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
        transition: 'none',
    };
}

// Solid title/close bar above the corner video, so our chrome stays readable
// over YouTube's own overlay. Added to the corner box height (not the inline dock).
const HEADER_HEIGHT = 40;

function cornerBox() {
    const width = Math.min(400, window.innerWidth - 32);
    const height = width * (9 / 16) + HEADER_HEIGHT;

    return { width, height, top: window.innerHeight - height - 16, left: window.innerWidth - width - 16 };
}

function applyCorner(animate) {
    const box = cornerBox();

    geom.value = {
        position: 'fixed',
        top: `${box.top}px`,
        left: `${box.left}px`,
        width: `${box.width}px`,
        height: `${box.height}px`,
        transition: animate ? 'top .3s ease, left .3s ease, width .3s ease, height .3s ease' : 'none',
    };
}

// Pop out: freeze at the current on-screen spot, then animate to the corner.
function popOut() {
    const el = videoWrap.value;

    if (!el) {
        applyCorner(false);

        return;
    }

    const rect = el.getBoundingClientRect();

    geom.value = {
        position: 'fixed',
        top: `${rect.top}px`,
        left: `${rect.left}px`,
        width: `${rect.width}px`,
        height: `${rect.height}px`,
        transition: 'none',
    };

    requestAnimationFrame(() => requestAnimationFrame(() => applyCorner(true)));
}

watch(() => player.dockEl, (el) => {
    if (el) {
        nextTick(applyInline);
    } else if (isVideo.value) {
        popOut();
    }
});

watch(isVideo, (active) => {
    if (active) {
        nextTick(() => (player.dockEl ? applyInline() : applyCorner(false)));
    }
});

function onResize() {
    if (player.dockEl) {
        applyInline();
    } else if (isVideo.value) {
        applyCorner(false);
    }
}

// ---- video (Plyr) ----------------------------------------------------------
// Build the right embed for the source: a YouTube/Vimeo provider div, or a
// native <video> element for a direct file (mp4/webm/...).
function loadVideo(source) {
    nextTick(() => {
        const target = plyrTarget.value;

        if (!target || !source) {
            return;
        }

        if (plyr) {
            try {
                plyr.destroy();
            } catch {
                // already gone
            }
            plyr = null;
        }

        target.innerHTML = source.provider === 'html5'
            ? `<video playsinline><source src="${source.src}" type="${source.mime}"></video>`
            : `<div data-plyr-provider="${source.provider}" data-plyr-embed-id="${source.id}"></div>`;

        plyr = new Plyr(target.firstElementChild, {
            youtube: { noCookie: true, rel: 0, modestbranding: 1, playsinline: 1 },
            autoplay: true,
        });

        plyr.on('ready', () => plyr?.play());
    });
}

watch(
    () => (isVideo.value ? player.track.videoUrl : null),
    (url) => {
        if (url) {
            loadVideo(videoSource(url));
        }
    },
);

// ---- audio (native element) ------------------------------------------------
watch(
    () => (isAudio.value ? player.track.audioUrl : null),
    (url) => {
        const el = audioEl.value;

        if (!el || !url) {
            return;
        }

        if (el.src !== url) {
            el.src = url;
            el.load();
        }

        if (player.playing) {
            el.play().catch(() => {});
        }
    },
);

watch(
    () => player.playing,
    (playing) => {
        const el = audioEl.value;

        if (!el || !isAudio.value) {
            return;
        }

        if (playing) {
            el.play().catch(() => {});
        } else {
            el.pause();
        }
    },
);

// Only one medium at a time.
watch(() => player.mode, (mode) => {
    if (mode !== 'audio') {
        audioEl.value?.pause();
    }

    if (mode !== 'video') {
        plyr?.pause();
    }
});

function onTimeUpdate() {
    currentTime.value = audioEl.value?.currentTime ?? 0;
}

function onLoaded() {
    duration.value = audioEl.value?.duration ?? 0;
}

function onEnded() {
    player.playing = false;
}

function seek(event) {
    const el = audioEl.value;

    if (!el || !duration.value) {
        return;
    }

    const rect = event.currentTarget.getBoundingClientRect();
    el.currentTime = ((event.clientX - rect.left) / rect.width) * duration.value;
}

function clock(seconds) {
    if (!seconds || Number.isNaN(seconds)) {
        return '0:00';
    }

    const minutes = Math.floor(seconds / 60);
    const remainder = Math.floor(seconds % 60);

    return `${minutes}:${String(remainder).padStart(2, '0')}`;
}

onMounted(() => {
    window.addEventListener('resize', onResize);
});

onBeforeUnmount(() => {
    plyr?.destroy();
    window.removeEventListener('resize', onResize);
});
</script>

<template>
    <div>
        <audio ref="audioEl" @timeupdate="onTimeUpdate" @loadedmetadata="onLoaded" @ended="onEnded" />

        <div v-if="isAudio" class="fixed inset-x-0 bottom-0 z-50 border-t border-neutral-50 bg-neutral-0 md:pl-66">
            <div class="mx-auto flex max-w-4xl items-center gap-4 px-5 py-3 md:px-10">
                <img v-if="player.track.thumbnail" :src="player.track.thumbnail" alt="" class="size-11 shrink-0 rounded-md object-cover">
                <Button
                    variant="primary"
                    size="icon"
                    pill
                    class="size-10 shrink-0"
                    :aria-label="player.playing ? 'Pause' : 'Play'"
                    @click="togglePlay"
                >
                    <Icon :icon="player.playing ? PauseIcon : PlayIcon" class="size-5" />
                </Button>
                <div class="min-w-0 flex-1">
                    <component
                        :is="player.track.url ? Link : 'div'"
                        :href="player.track.url || undefined"
                        class="block truncate text-meta font-semibold text-neutral-900"
                        :class="player.track.url ? 'transition-colors hover:text-accent-500' : ''"
                    >{{ player.track.title }}</component>
                    <div class="mt-1 flex items-center gap-2">
                        <span class="text-label text-neutral-500 tnum">{{ clock(currentTime) }}</span>
                        <div class="relative h-1.5 flex-1 cursor-pointer rounded-full bg-neutral-100" @click="seek">
                            <div class="absolute inset-y-0 left-0 rounded-full bg-accent-500" :style="{ width: progress + '%' }" />
                        </div>
                        <span class="text-label text-neutral-500 tnum">{{ clock(duration) }}</span>
                    </div>
                </div>
                <button type="button" class="shrink-0 text-neutral-500 transition-colors hover:text-neutral-900" aria-label="Close player" @click="closePlayer">
                    <Icon :icon="Cancel01Icon" class="size-5" />
                </button>
            </div>
        </div>

        <Teleport to="body">
            <div
                v-show="isVideo"
                ref="videoWrap"
                class="z-50 flex flex-col overflow-hidden bg-black"
                :style="geom"
                :class="player.dockEl ? 'rounded-lg' : 'rounded-lg border border-neutral-50 shadow-card'"
            >
                <div
                    v-if="isVideo && !player.dockEl"
                    class="flex h-10 shrink-0 items-center justify-between gap-2 border-b border-neutral-50 bg-neutral-0 px-3"
                >
                    <component
                        :is="player.track.url ? Link : 'span'"
                        :href="player.track.url || undefined"
                        class="truncate text-caption font-semibold text-neutral-900"
                        :class="player.track.url ? 'transition-colors hover:text-accent-500' : ''"
                    >{{ player.track.title }}</component>
                    <button type="button" class="shrink-0 text-neutral-500 transition-colors hover:text-neutral-900" aria-label="Close player" @click="closePlayer">
                        <Icon :icon="Cancel01Icon" class="size-4" />
                    </button>
                </div>
                <div ref="plyrTarget" class="min-h-0 w-full flex-1"></div>
            </div>
        </Teleport>
    </div>
</template>
