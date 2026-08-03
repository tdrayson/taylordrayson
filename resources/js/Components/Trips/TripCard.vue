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
            <div class="w-28 shrink-0 rounded-xl border border-neutral-100 px-4 py-3">
                <div class="type-color text-label uppercase">{{ start.month }} {{ start.year }}</div>
                <div class="mt-1 font-display text-stat leading-none tnum">{{ start.day }}</div>
                <time :datetime="start.iso" class="mt-1.5 block text-caption text-neutral-500 tnum">{{ start.time }}</time>
            </div>

            <div class="flex flex-1 flex-col items-center gap-1.5">
                <span class="text-label uppercase text-neutral-500 tnum">{{ days }} {{ days === 1 ? 'day' : 'days' }}</span>
                <span class="h-px w-full bg-neutral-100" />
            </div>

            <div class="w-28 shrink-0 rounded-xl border border-neutral-100 px-4 py-3 text-right">
                <div class="type-color text-label uppercase">{{ end.month }} {{ end.year }}</div>
                <div class="mt-1 font-display text-stat leading-none tnum">{{ end.day }}</div>
                <time :datetime="end.iso" class="mt-1.5 block text-caption text-neutral-500 tnum">{{ end.time }}</time>
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
