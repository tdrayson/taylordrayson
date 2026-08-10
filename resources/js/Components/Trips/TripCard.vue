<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    title: { type: String, required: true },
    href: { type: String, required: true },
    // { day, month, year, time, label, full, iso, offset }, formatted
    // server-side in the trip's own timezone so the client never does date maths.
    start: { type: Object, required: true },
    end: { type: Object, required: true },
    days: { type: Number, required: true },
});

// The full start timestamp with its offset, shown on hovering the time and used
// as the link's accessible name, matching FeedItem's own timestamp treatment.
const fullTimestamp = computed(() => `${props.start.full} ${props.start.offset}`.trim());
</script>

<template>
    <!-- Mirrors FeedItem's rail so the trip index reads as the same kind of feed.
         Trips borrow the flight accent rather than minting a fourteenth colour. -->
    <div class="relative block" :style="{ '--type-color': 'var(--color-flight)' }">
        <span class="type-color absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 lg:-left-12">
            <Icon name="Luggage01Icon" class="size-5" />
        </span>

        <div class="flex min-h-9 items-center">
            <div class="flex items-baseline gap-2.5">
                <Link :href="href" class="type-color text-label uppercase underline-offset-2 hover:underline focus-visible:underline">Trip</Link>
                <Tooltip :label="fullTimestamp" placement="top">
                    <Link :href="href" :aria-label="fullTimestamp" class="underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline">
                        <time :datetime="start.iso" class="text-xs text-neutral-500 tnum transition-colors hover:text-accent-500">{{ start.time }}</time>
                    </Link>
                </Tooltip>
            </div>
        </div>

        <h3 class="mt-1 font-display text-item-title">
            <Link :href="href" class="transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ title }}</Link>
        </h3>

        <!-- Each end carries its own full date, since a trip title names a place
             and never the year. -->
        <div class="mt-3 flex max-w-md items-center gap-4">
            <div class="tile-border flex w-20 shrink-0 flex-col overflow-hidden rounded-xl border">
                <div class="tile-band py-1 text-center text-label uppercase text-white">{{ start.month }}</div>
                <div class="flex flex-col items-center justify-center gap-0.5 p-2">
                    <time :datetime="start.iso" class="tile-day font-display text-stat leading-none tnum">{{ start.day }}</time>
                    <span class="text-caption text-neutral-400 tnum">{{ start.year }}</span>
                </div>
            </div>

            <div class="flex flex-1 flex-col items-center gap-1">
                <span class="text-label uppercase text-neutral-500 tnum">{{ days }} {{ days === 1 ? 'day' : 'days' }}</span>
                <div class="relative flex w-full items-center justify-center">
                    <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-neutral-100" />
                    <span class="relative bg-neutral-0 px-2 text-neutral-500">
                        <Icon name="Luggage01Icon" class="size-4" />
                    </span>
                </div>
            </div>

            <div class="tile-border flex w-20 shrink-0 flex-col overflow-hidden rounded-xl border">
                <div class="tile-band py-1 text-center text-label uppercase text-white">{{ end.month }}</div>
                <div class="flex flex-col items-center justify-center gap-0.5 p-2">
                    <time :datetime="end.iso" class="tile-day font-display text-stat leading-none tnum">{{ end.day }}</time>
                    <span class="text-caption text-neutral-400 tnum">{{ end.year }}</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* Mirrors FeedItem's own rule: the eyebrow and rail bubble take the type colour
   set on the wrapper, which for a trip is the flight accent. */
.type-color {
    color: var(--type-color);
}

/* The calendar tile: accent border and month band, accent day number. Set here
   rather than with arbitrary Tailwind values so the colour follows the single
   --type-color the card already declares. */
.tile-border {
    border-color: var(--type-color);
}

.tile-band {
    background: var(--type-color);
}

.tile-day {
    color: var(--type-color);
}
</style>
