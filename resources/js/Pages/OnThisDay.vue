<script setup>
import { computed } from 'vue';
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import TimelineFeed from '../Components/Timeline/TimelineFeed.vue';
import Pill from '../Components/Ui/Pill.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    date: { type: String, required: true }, // today's day + month, e.g. "5 July"
    entriesCount: { type: Number, default: 0 },
    yearsCount: { type: Number, default: 0 },
    groups: { type: Array, default: () => [] },
});

// "12 entries across 4 years, on 5 July" — falls back to just the date when
// nothing has ever landed on today.
const subtitle = computed(() => {
    if (!props.entriesCount) {
        return props.date;
    }

    const entries = `${props.entriesCount.toLocaleString('en-GB')} ${props.entriesCount === 1 ? 'entry' : 'entries'}`;
    const years = `${props.yearsCount} ${props.yearsCount === 1 ? 'year' : 'years'}`;

    return `${entries} across ${years}, on ${props.date}`;
});

// The chip beside each year: "This year" for the current year, else "N years
// ago". Derived from group.date client-side so a cached page never goes stale.
function relativeLabel(date) {
    const diff = new Date().getFullYear() - Number(date.slice(0, 4));

    if (diff === 0) {
        return 'This year';
    }

    return `${diff} ${diff === 1 ? 'year' : 'years'} ago`;
}

setLayoutProps({
    breadcrumb: [{ label: 'On this day' }],
});
</script>

<template>
    <AppHead :og="og" />

    <!-- Not a data-type page: plain H1, no eyebrow. -->
    <ViewHeader title="On this day" :subtitle="subtitle" />

    <div v-if="groups.length" class="mt-10 flex flex-col gap-14">
        <section v-for="group in groups" :key="group.date">
            <h2 class="mb-6 flex items-center gap-3 font-display text-item-title">
                <Link
                    :href="group.href"
                    class="underline-offset-4 transition-colors hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline"
                >
                    <time :datetime="group.date">{{ group.date.slice(0, 4) }}</time>
                </Link>
                <Pill :label="relativeLabel(group.date)" />
            </h2>
            <TimelineFeed :items="group.items" />
        </section>
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing logged on {{ date }} in any other year yet.</p>
</template>
