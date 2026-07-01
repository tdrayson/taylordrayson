<script setup>
import { ref, onMounted } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    unit: { type: String, default: 'page' }, // 'day' | 'month' | 'year'
});

const SRC = 'https://media.tenor.com/5RoOVuaHQZUAAAPo/intergalactic-quality-intergalactic.mp4';

const video = ref(null);
let playsLeft = 0;

// Restart from the first frame and play once (loop off).
function start() {
    const el = video.value;

    if (!el) {
        return;
    }

    el.loop = false;
    el.currentTime = 0;
    el.play().catch(() => {});
}

// Plays through twice on load, then freezes on the final frame.
function autoPlay() {
    playsLeft = 2;
    start();
}

function onEnded() {
    playsLeft -= 1;

    if (playsLeft > 0) {
        start();
    }
}

// Hover loops it continuously; leaving freezes it where it is.
function onEnter() {
    const el = video.value;

    if (!el) {
        return;
    }

    playsLeft = 0;
    el.loop = true;
    el.currentTime = 0;
    el.play().catch(() => {});
}

function onLeave() {
    const el = video.value;

    if (el) {
        el.loop = false;
        el.pause();
    }
}

onMounted(() => {
    if (video.value) {
        video.value.muted = true;
    }

    autoPlay();
});
</script>

<template>
    <div class="flex flex-col items-center gap-6 py-16 text-center">
        <video
            ref="video"
            :src="SRC"
            muted
            playsinline
            preload="auto"
            width="498"
            height="269"
            class="w-full max-w-2xl cursor-pointer rounded-lg border border-neutral-50"
            @ended="onEnded"
            @mouseenter="onEnter"
            @mouseleave="onLeave"
            @click="autoPlay"
        />
        <div class="max-w-xl space-y-4">
            <p class="font-display text-stat">Hold your horses — I'm not a time traveller.</p>
            <p class="text-lg text-neutral-500">
                This {{ unit }} hasn't happened yet, and my flux capacitor's on the fritz. I only log life as
                it actually happens, so there's genuinely nothing here. Come back once we've hit 88&nbsp;mph.
            </p>
        </div>
        <Link
            href="/"
            class="inline-flex items-center gap-1.5 text-meta font-semibold text-accent-500 transition-colors hover:text-accent-700 focus-visible:text-accent-700"
        >
            <Icon :icon="ArrowLeft01Icon" class="size-4" />
            Back to the present
        </Link>
    </div>
</template>
