<script setup>
import { computed } from 'vue';
import { setLayoutProps, Deferred } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import Heatmap from '../Components/Stats/Heatmap.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
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
});

const isFuture = computed(() => props.year > new Date().getFullYear());
const subtitle = computed(() => `${props.entriesCount.toLocaleString('en-GB')} ${props.entriesCount === 1 ? 'entry' : 'entries'} logged`);

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

        <StatGrid v-if="stats.length" :stats="stats" size="lg" class="mt-8" />

        <section v-if="entriesCount">
            <SectionHead title="The year" :meta="`${Object.keys(heatmap).length} days logged`" />
            <Heatmap :days="heatmap" :year="year" />
        </section>

        <section v-if="entriesCount">
            <SectionHead title="Everything" meta="oldest first" />
            <Deferred data="groups">
                <template #fallback>
                    <!-- Pulsing skeleton while the tail loads. -->
                    <div class="space-y-6">
                        <div v-for="i in 3" :key="i" class="animate-pulse space-y-3">
                            <div class="h-6 w-48 rounded-md bg-neutral-25" />
                            <div class="h-24 rounded-lg bg-neutral-25" />
                        </div>
                    </div>
                </template>

                <div class="flex flex-col gap-14">
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
                :prev-url="currentPage > 1 ? `/${year}?page=${currentPage - 1}` : null"
                :next-url="currentPage < lastPage ? `/${year}?page=${currentPage + 1}` : null"
            />
        </section>

        <p v-if="!entriesCount" class="mt-10 text-meta text-neutral-500">Nothing logged in {{ year }}.</p>
    </template>
</template>
