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
import PhotoGrid from '../Components/Ui/PhotoGrid.vue';
import Button from '../Components/Ui/Button.vue';
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

const PHOTO_PREVIEW = 12;
const showAllPhotos = ref(false);

// A busy month can run to 35 photos, which pushes the day-by-day feed several
// screens down, so the strip opens at a screenful. The rest are already in the
// payload, so revealing them costs no request.
const visiblePhotos = computed(() => (showAllPhotos.value ? props.photos : props.photos.slice(0, PHOTO_PREVIEW)));

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
        { label: monthName.value, ariaLabel: `${monthName.value} ${props.year}` },
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

        <!-- All three summarise the whole month, so later pages of the feed omit
             them (the server sends none of them past page 1). -->
        <template v-if="currentPage === 1">
            <StatGrid v-if="stats.length" :stats="stats" class="mt-8" />

            <CalendarMonth :year="year" :month="month" :days="days" />

            <section v-if="photos.length">
                <SectionHead title="Photos" size="title" :meta="`${photos.length} this month`" />
                <!-- Same masonry + hover-context tiles and column count as /photos. -->
                <PhotoGrid :photos="visiblePhotos" :columns="3" @open="lightboxIndex = $event" />
                <Lightbox v-model:index="lightboxIndex" :photos="visiblePhotos" />

                <div v-if="!showAllPhotos && photos.length > PHOTO_PREVIEW" class="mt-6 flex justify-center">
                    <Button @click="showAllPhotos = true">Show all {{ photos.length }} photos</Button>
                </div>
            </section>
        </template>

        <section v-if="entriesCount" class="mt-12">
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

        <p v-if="!entriesCount" class="mt-10 text-meta text-neutral-500">Nothing logged in {{ monthName }} {{ year }}.</p>
    </template>
</template>
