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
    // True when the trip crosses a year boundary, so both tiles show their year.
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

        <!-- A calendar tile per end of the window, month above day, with the
             span between them. -->
        <div class="mt-3 flex items-center gap-4">
            <div class="flex w-16 flex-col overflow-hidden rounded-lg border border-neutral-100">
                <span class="type-color-bg py-0.5 text-center text-label uppercase text-white">{{ start.month }}</span>
                <span class="bg-neutral-0 py-1 text-center font-display text-section tnum">{{ start.day }}</span>
                <span v-if="spansYears" class="bg-neutral-0 pb-1 text-center text-caption text-neutral-400 tnum">{{ start.year }}</span>
            </div>

            <span class="text-label uppercase text-neutral-400 tnum">{{ days }} {{ days === 1 ? 'day' : 'days' }}</span>

            <div class="flex w-16 flex-col overflow-hidden rounded-lg border border-neutral-100">
                <span class="type-color-bg py-0.5 text-center text-label uppercase text-white">{{ end.month }}</span>
                <span class="bg-neutral-0 py-1 text-center font-display text-section tnum">{{ end.day }}</span>
                <span v-if="spansYears" class="bg-neutral-0 pb-1 text-center text-caption text-neutral-400 tnum">{{ end.year }}</span>
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

/* The tile's month band, filled with the same accent. */
.type-color-bg {
    background: var(--type-color);
}
</style>
