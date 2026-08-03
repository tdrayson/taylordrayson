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
    // True when the trip crosses a year boundary, so both ends show their year.
    spansYears: { type: Boolean, default: false },
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

        <!-- Start date, span, end date: the same three-part strip a flight card
             uses for origin, duration and destination. -->
        <div class="mt-3 flex max-w-md items-center justify-between gap-4">
            <div class="shrink-0">
                <div class="font-display text-stat tnum">{{ start.day }}</div>
                <div class="font-display text-section">{{ spansYears ? `${start.month} ${start.year}` : start.month }}</div>
                <div class="text-caption text-neutral-500 tnum">{{ start.time }}</div>
            </div>

            <div class="flex flex-1 flex-col items-center gap-1">
                <div class="text-label uppercase text-neutral-500 tnum">{{ days }} {{ days === 1 ? 'day' : 'days' }}</div>
                <div class="relative flex w-full items-center justify-center">
                    <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-neutral-100" />
                    <span class="relative bg-neutral-0 px-2 text-neutral-500">
                        <Icon name="Luggage01Icon" class="size-4" />
                    </span>
                </div>
            </div>

            <div class="shrink-0 text-right">
                <div class="font-display text-stat tnum">{{ end.day }}</div>
                <div class="font-display text-section">{{ spansYears ? `${end.month} ${end.year}` : end.month }}</div>
                <div class="text-caption text-neutral-500 tnum">{{ end.time }}</div>
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
</style>
