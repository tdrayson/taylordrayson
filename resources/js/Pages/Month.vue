<script setup>
import { computed, ref } from 'vue';
import { setLayoutProps, Deferred } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/Layout/ViewHeader.vue';
import StatGrid from '../Components/Stats/StatGrid.vue';
import CalendarMonth from '../Components/Stats/CalendarMonth.vue';
import SectionHead from '../Components/Ui/SectionHead.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import ZoomButton from '../Components/Ui/ZoomButton.vue';
import Lightbox from '../Components/Overlays/Lightbox.vue';
import FutureNote from '../Components/Timeline/FutureNote.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    og: { type: Object, default: () => ({}) },
    entriesCount: { type: Number, default: 0 },
    days: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
    photos: { type: Array, default: () => [] },
    groups: { type: Array, default: null }, // deferred
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

// Which photo the lightbox is showing (null = closed).
const lightboxIndex = ref(null);

const pad = (value) => String(value).padStart(2, '0');
const date = computed(() => new Date(props.year, props.month - 1, 1));
const isFuture = computed(() => {
    const now = new Date();

    return date.value.getTime() > new Date(now.getFullYear(), now.getMonth(), 1).getTime();
});
const monthName = computed(() => date.value.toLocaleDateString('en-GB', { month: 'long' }));
const subtitle = computed(() => (isFuture.value ? '' : `${props.entriesCount} ${props.entriesCount === 1 ? 'entry' : 'entries'} this month`));

function monthUrl(value) {
    return `/${value.getFullYear()}/${pad(value.getMonth() + 1)}`;
}

function monthLabel(value) {
    return value.toLocaleDateString('en-GB', { month: 'long' });
}

const prevMonth = computed(() => new Date(props.year, props.month - 2, 1));
const nextMonth = computed(() => new Date(props.year, props.month, 1));

setLayoutProps({
    breadcrumb: [
        { label: String(props.year), href: `/${props.year}` },
        { label: monthName.value },
    ],
});
</script>

<template>
    <AppHead :og="og" />

    <FutureNote v-if="isFuture" unit="month" />
    <template v-else>
        <ViewHeader
            :title="`${monthName} ${year}`"
            :subtitle="subtitle"
            :prev="{ label: monthLabel(prevMonth), href: monthUrl(prevMonth) }"
            :next="{ label: monthLabel(nextMonth), href: monthUrl(nextMonth) }"
        />

        <StatGrid v-if="stats.length" :stats="stats" class="mt-8" />

        <CalendarMonth :year="year" :month="month" :days="days" />

        <section v-if="photos.length">
            <SectionHead title="Photos" :meta="`${photos.length}${photos.length === 12 ? '+' : ''} this month`" />
            <div class="grid grid-cols-3 gap-2.5 sm:grid-cols-6">
                <button
                    v-for="(photo, index) in photos"
                    :key="index"
                    type="button"
                    :aria-label="`View photo ${index + 1}`"
                    class="group/zoom relative aspect-square overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 transition-opacity hover:opacity-95 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="lightboxIndex = index"
                >
                    <img :src="photo.src" :srcset="photo.srcset || undefined" sizes="(min-width: 768px) 16vw, 33vw" alt="" class="size-full object-cover">
                    <span class="pointer-events-none absolute right-2 top-2 opacity-0 transition-opacity group-hover/zoom:opacity-100 group-focus-within/zoom:opacity-100">
                        <ZoomButton />
                    </span>
                </button>
            </div>
            <Lightbox v-model:index="lightboxIndex" :photos="photos" />
        </section>

        <section>
            <SectionHead title="Everything" meta="oldest first" />
            <Deferred data="groups">
                <template #fallback>
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
                :prev-url="currentPage > 1 ? (currentPage - 1 === 1 ? `/${year}/${pad(month)}` : `/${year}/${pad(month)}?page=${currentPage - 1}`) : null"
                :next-url="currentPage < lastPage ? `/${year}/${pad(month)}?page=${currentPage + 1}` : null"
            />
        </section>
    </template>
</template>
