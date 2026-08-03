<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

defineProps({
    title: { type: String, required: true },
    href: { type: String, required: true },
    // { day, month, year, time, label, iso, offset }, formatted server-side in
    // the trip's own timezone so the client never does date maths.
    start: { type: Object, required: true },
    end: { type: Object, required: true },
    days: { type: Number, required: true },
});
</script>

<template>
    <!-- Mirrors FeedItem's rail treatment (icon bubble hung off the left) so the
         trip index reads as the same kind of feed as the timeline itself. Trips
         borrow the flight accent rather than minting a fourteenth type colour. -->
    <div class="relative block" :style="{ '--type-color': 'var(--color-flight)' }">
        <span class="type-color absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 lg:-left-12">
            <Icon name="Luggage01Icon" class="size-5" />
        </span>

        <!-- Type label and start time on one baseline, the way a timeline card
             pairs its type with its timestamp. -->
        <div class="flex min-h-9 items-center">
            <div class="flex items-baseline gap-2.5">
                <Link :href="href" class="type-color text-label uppercase underline-offset-2 hover:underline focus-visible:underline">Trip</Link>
                <time :datetime="start.iso" class="text-xs text-neutral-500 tnum">{{ start.time }}</time>
            </div>
        </div>

        <h3 class="mt-1 font-display text-item-title">
            <Link :href="href" class="transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ title }}</Link>
        </h3>

        <!-- Each end carries its own full date and time, since a trip title
             names a place and never the year. The span sits above the rule
             joining them. -->
        <div class="mt-3 flex max-w-md items-center gap-4">
            <!-- The body below the band is square, so the date block keeps a
                 calendar page's proportions whatever the day number's width. -->
            <div class="tile-border flex w-16 shrink-0 flex-col overflow-hidden rounded-xl border">
                <div class="tile-band py-1 text-center text-label uppercase text-white">{{ start.month }}</div>
                <div class="flex aspect-square flex-col items-center justify-center gap-0.5">
                    <time :datetime="start.iso" class="tile-day font-display text-stat leading-none tnum">{{ start.day }}</time>
                    <span class="text-caption text-neutral-400 tnum">{{ start.year }}</span>
                </div>
            </div>

            <!-- Span above a rule broken by the trip icon, the way a flight
                 card hangs its duration over the plane. -->
            <div class="flex flex-1 flex-col items-center gap-1">
                <span class="text-label uppercase text-neutral-500 tnum">{{ days }} {{ days === 1 ? 'day' : 'days' }}</span>
                <div class="relative flex w-full items-center justify-center">
                    <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-neutral-100" />
                    <span class="relative bg-neutral-0 px-2 text-neutral-500">
                        <Icon name="Luggage01Icon" class="size-4" />
                    </span>
                </div>
            </div>

            <div class="tile-border flex w-16 shrink-0 flex-col overflow-hidden rounded-xl border">
                <div class="tile-band py-1 text-center text-label uppercase text-white">{{ end.month }}</div>
                <div class="flex aspect-square flex-col items-center justify-center gap-0.5">
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
