<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import IntroBlock from '../Components/Timeline/IntroBlock.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import YearJump from '../Components/Timeline/YearJump.vue';
import { formatRange } from '../lib/dateFormat.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    groups: { type: Array, default: () => [] },
    // { from, to } as Y-m-d, the oldest and newest day on this page.
    range: { type: Object, default: null },
    olderUrl: { type: String, default: null },
    newerUrl: { type: String, default: null },
    // list<{ year, href }>, newest first.
    years: { type: Array, default: () => [] },
    thisWeekWithEpisodes: { type: Number, default: 0 },
});

setLayoutProps({
    breadcrumb: [],
});

// The intro only belongs on the front of the feed, which is now the page with
// nothing newer than it rather than page 1.
const isFront = computed(() => props.newerUrl === null);

/** The page's span as one line, e.g. "1-22 Sep 2026". */
const rangeLabel = computed(() => (props.range ? formatRange(props.range.from, props.range.to) : ''));

// The year the page sits in, for the jump control to mark. Null when it straddles two.
const currentYear = computed(() => {
    if (!props.range) {
        return null;
    }

    const from = Number(props.range.from.slice(0, 4));

    return from === Number(props.range.to.slice(0, 4)) ? from : null;
});
</script>

<template>
    <AppHead :og="og" />

    <IntroBlock v-if="isFront" :this-week-with-episodes="thisWeekWithEpisodes" class="mb-14" />

    <div v-if="groups.length" class="h-feed flex flex-col gap-14">
        <h1 class="p-name sr-only">Taylor Drayson timeline</h1>
        <AuthorRef />
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
        />
    </div>

    <p v-else class="text-sm text-neutral-500">No entries yet.</p>

    <div v-if="olderUrl || newerUrl" class="mt-14">
        <Pagination
            prev-label="Newer"
            next-label="Older"
            :prev-url="newerUrl"
            :next-url="olderUrl"
        >
            <template #label>{{ rangeLabel }}</template>
        </Pagination>

        <YearJump :years="years" :current="currentYear" />
    </div>
</template>

