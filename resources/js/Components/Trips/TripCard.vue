<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Heading from '../Ui/Heading.vue';
import Stat from '../Ui/Stat.vue';

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
        <span class="absolute -left-14 top-px flex size-9 items-center justify-center rounded-full bg-neutral-25 text-(--type-color) lg:-left-12">
            <Icon name="Luggage01Icon" class="size-5" />
        </span>

        <div class="flex min-h-9 items-center">
            <div class="flex items-baseline gap-2.5">
                <Link :href="href" class="text-2xs font-semibold uppercase tracking-wider text-(--type-color) underline-offset-2 hover:underline focus-visible:underline">Trip</Link>
                <Tooltip :label="fullTimestamp" placement="top">
                    <Link :href="href" :aria-label="fullTimestamp" class="underline-offset-2 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline">
                        <time :datetime="start.iso" class="text-xs text-neutral-500 tabular-nums transition-colors hover:text-accent-500">{{ start.time }}</time>
                    </Link>
                </Tooltip>
            </div>
        </div>

        <Heading as="h3" size="title" class="mt-1">
            <Link :href="href" class="transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ title }}</Link>
        </Heading>

        <!-- Each end carries its own full date, since a trip title names a place
             and never the year. -->
        <div class="mt-3 flex max-w-md items-center gap-4">
            <div class="flex w-20 shrink-0 flex-col overflow-hidden rounded-xl border border-(--type-color)">
                <Eyebrow class="bg-(--type-color) py-1 text-center text-white">{{ start.month }}</Eyebrow>
                <div class="flex flex-col items-center justify-center gap-0.5 p-2">
                    <Stat as="time" :datetime="start.iso" class="text-(--type-color)">{{ start.day }}</Stat>
                    <span class="text-xs text-neutral-400 tabular-nums">{{ start.year }}</span>
                </div>
            </div>

            <div class="flex flex-1 flex-col items-center gap-1">
                <Eyebrow as="span" class="text-neutral-500 tabular-nums">{{ days }} {{ days === 1 ? 'day' : 'days' }}</Eyebrow>
                <div class="relative flex w-full items-center justify-center">
                    <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-neutral-100" />
                    <span class="relative bg-neutral-0 px-2 text-neutral-500">
                        <Icon name="Luggage01Icon" class="size-4" />
                    </span>
                </div>
            </div>

            <div class="flex w-20 shrink-0 flex-col overflow-hidden rounded-xl border border-(--type-color)">
                <Eyebrow class="bg-(--type-color) py-1 text-center text-white">{{ end.month }}</Eyebrow>
                <div class="flex flex-col items-center justify-center gap-0.5 p-2">
                    <Stat as="time" :datetime="end.iso" class="text-(--type-color)">{{ end.day }}</Stat>
                    <span class="text-xs text-neutral-400 tabular-nums">{{ end.year }}</span>
                </div>
            </div>
        </div>
    </div>
</template>
