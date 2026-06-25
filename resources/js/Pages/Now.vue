<script setup>
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import ChargingWidget from '../Components/Now/widgets/ChargingWidget.vue';
import ActivityWidget from '../Components/Now/widgets/ActivityWidget.vue';
import WeatherWidget from '../Components/Now/widgets/WeatherWidget.vue';
import PhotosWidget from '../Components/Now/widgets/PhotosWidget.vue';
import TimeWidget from '../Components/Now/widgets/TimeWidget.vue';
import SleepWidget from '../Components/Now/widgets/SleepWidget.vue';
import EntriesWidget from '../Components/Now/widgets/EntriesWidget.vue';
import ReadingWidget from '../Components/Now/widgets/ReadingWidget.vue';
import LocationWidget from '../Components/Now/widgets/LocationWidget.vue';
import PodcastWidget from '../Components/Now/widgets/PodcastWidget.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    episode: { type: Object, default: null },
});

setLayoutProps({
    breadcrumb: [{ label: 'Now' }],
});

const latelyPhotos = [
    { src: 'https://static.photos/people/640x360/47', gradient: 'linear-gradient(135deg, #d6c2b2, #b89a86)' },
    { src: 'https://static.photos/nature/640x360/204', gradient: 'linear-gradient(135deg, #bcd3e6, #8fb0cf)' },
    { src: 'https://static.photos/travel/640x360/88', gradient: 'linear-gradient(135deg, #d9c7b0, #c2a47e)' },
    { src: 'https://static.photos/food/640x360/15', gradient: 'linear-gradient(135deg, #cfe0cd, #9cc09a)' },
    { src: 'https://static.photos/animals/640x360/33', gradient: 'linear-gradient(135deg, #e6cdd6, #cf9ab0)' },
    { src: 'https://static.photos/sports/640x360/120', gradient: 'linear-gradient(135deg, #c8c4e6, #9a8fd0)' },
];
</script>

<template>
    <Head title="Now" />

    <div class="breakout mx-auto w-full max-w-5xl">
        <header>
            <h1 class="font-display text-display">Now</h1>
            <p class="mt-2 text-meta text-neutral-500">A live snapshot of my world, ticking away right this second.</p>
        </header>

        <div class="now-grid mt-8 grid grid-flow-row-dense grid-cols-2 gap-3 sm:gap-4 lg:grid-cols-4">
            <!-- Bento mosaic: a photos hero woven through small status tiles and
                 wide body/content bars rather than uniform rows. -->
            <TimeWidget />
            <LocationWidget />
            <ActivityWidget variant="dark" fill class="col-span-2" />
            <ChargingWidget device="iPhone" :percent="72" time-left="25 min left" charging />
            <WeatherWidget condition="hot" />
            <PhotosWidget class="col-span-2 row-span-2" :photos="latelyPhotos" />
            <SleepWidget class="col-span-2" />
            <PodcastWidget :episode="episode" />
            <EntriesWidget />
            <ReadingWidget fill class="col-span-2" />
        </div>
    </div>
</template>

<style scoped>
/* Staggered entrance: each widget fades and lifts into place on load. Their
   own internal animations (rings, charging glow, etc.) play on top. */
@keyframes widget-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.985);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

.now-grid > * {
    animation: widget-in 0.55s cubic-bezier(0.22, 1, 0.36, 1) both;
}

/* Raise the hovered widget above its siblings so its tooltips aren't clipped
   behind later widgets (e.g. the photos deck). */
.now-grid > *:hover {
    position: relative;
    z-index: 30;
}

.now-grid > *:nth-child(2) {
    animation-delay: 0.07s;
}

.now-grid > *:nth-child(3) {
    animation-delay: 0.14s;
}

.now-grid > *:nth-child(4) {
    animation-delay: 0.21s;
}

.now-grid > *:nth-child(5) {
    animation-delay: 0.28s;
}

.now-grid > *:nth-child(6) {
    animation-delay: 0.35s;
}

.now-grid > *:nth-child(7) {
    animation-delay: 0.42s;
}

.now-grid > *:nth-child(8) {
    animation-delay: 0.49s;
}

.now-grid > *:nth-child(9) {
    animation-delay: 0.56s;
}

.now-grid > *:nth-child(10) {
    animation-delay: 0.63s;
}

@media (prefers-reduced-motion: reduce) {
    .now-grid > * {
        animation: none;
    }
}
</style>
