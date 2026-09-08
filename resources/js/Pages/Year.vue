<script setup>
import { computed } from 'vue';
import { setLayoutProps, Deferred } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import MonthStrip from '../Components/Timeline/MonthStrip.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import FeedSkeleton from '../Components/Ui/FeedSkeleton.vue';
import Heatmap from '../Components/Stats/Heatmap.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';
import FutureNote from '../Components/Timeline/FutureNote.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    og: { type: Object, default: () => ({}) },
    entriesCount: { type: Number, default: 0 },
    stats: { type: Array, default: () => [] },
    heatmap: { type: Object, default: () => ({}) },
    groups: { type: Array, default: null }, // deferred
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    // list<{ month, label, href, total }>, January first, every month present.
    months: { type: Array, default: () => [] },
});

const isFuture = computed(() => props.year > new Date().getFullYear());
const subtitle = computed(() => `${props.entriesCount.toLocaleString('en-GB')} ${props.entriesCount === 1 ? 'entry' : 'entries'} logged`);
// The heatmap is still filling in for the current year ("so far"); past years are complete ("at a glance").
const heatmapTitle = computed(() => (props.year === new Date().getFullYear() ? `${props.year} so far` : `${props.year} at a glance`));

setLayoutProps({
    breadcrumb: [{ label: String(props.year) }],
});
</script>

<template>
    <AppHead :og="og" />

    <FutureNote v-if="isFuture" unit="year" />

    <template v-else>
        <!-- Not a data-type page: plain H1, no eyebrow. -->
        <ViewHeader
            :title="String(year)"
            :subtitle="subtitle"
            :prev="{ label: String(year - 1), href: `/${year - 1}` }"
            :next="{ label: String(year + 1), href: `/${year + 1}` }"
        />

        <!-- On every page: the heatmap below is the only other way into a
             month, and it only renders on the first. -->
        <MonthStrip :year="year" :months="months" class="mt-6" />

        <!-- Both summarise the whole year, so later pages of the feed omit them
             (the server sends neither past page 1). -->
        <template v-if="currentPage === 1">
            <StatGrid v-if="stats.length" :stats="stats" class="mt-8" />

            <section v-if="entriesCount">
                <SectionHead :title="heatmapTitle" :meta="`${Object.keys(heatmap).length} days logged`" />
                <Heatmap :days="heatmap" :year="year" />
            </section>
        </template>

        <section v-if="entriesCount" class="mt-12">
            <Deferred data="groups">
                <template #fallback>
                    <FeedSkeleton />
                </template>

                <div class="h-feed flex flex-col gap-14">
                    <AuthorRef />
                    <DateGroup
                        v-for="group in groups"
                        :key="group.date"
                        :label="group.label"
                        :date="group.date"
                        :href="group.href"
                        :items="group.items"
                    />
                </div>
            </Deferred>

            <Pagination
                v-if="lastPage > 1"
                class="mt-14"
                :current-page="currentPage"
                :last-page="lastPage"
                :prev-url="currentPage > 1 ? (currentPage - 1 === 1 ? `/${year}` : `/${year}?page=${currentPage - 1}`) : null"
                :next-url="currentPage < lastPage ? `/${year}?page=${currentPage + 1}` : null"
            />
        </section>

        <p v-if="!entriesCount" class="mt-10 text-meta text-neutral-500">Nothing logged in {{ year }}.</p>
    </template>
</template>
