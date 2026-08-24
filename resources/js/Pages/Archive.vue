<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import TaxonomyFilter from '../Components/Ui/TaxonomyFilter.vue';
import FlightsMap from '../Components/Maps/FlightsMap.vue';
import StationsMap from '../Components/Maps/StationsMap.vue';
import PlacesMap from '../Components/Maps/PlacesMap.vue';
import { entryType } from '../entryTypes.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    type: { type: String, required: true },
    accent: { type: String, required: true },
    og: { type: Object, default: () => ({}) },
    title: { type: String, required: true },
    crumb: { type: String, default: '' },
    subtitle: { type: String, default: '' },
    groups: { type: Array, default: () => [] },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    chips: { type: Array, default: () => [] },
    tagLink: { type: Object, default: null },
    parent: { type: Object, default: null },
    map: { type: Array, default: () => [] },
});

const meta = computed(() => entryType(props.type));
const accentStyle = computed(() => ({ color: `var(--color-${props.accent})` }));

const path = computed(() => usePage().url.split('?')[0]);
const pageUrl = (page) => (page <= 1 ? path.value : `${path.value}?page=${page}`);
const prevUrl = computed(() => (props.currentPage > 1 ? pageUrl(props.currentPage - 1) : null));
const nextUrl = computed(() => (props.currentPage < props.lastPage ? pageUrl(props.currentPage + 1) : null));

setLayoutProps({
    breadcrumb: props.parent
        ? [{ label: props.parent.label, href: props.parent.href }, { label: props.crumb || props.title }]
        : [{ label: props.crumb || props.title }],
});
</script>

<template>
    <AppHead :og="og" />

    <header class="relative">
        <span class="absolute top-0 hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 lg:-left-16 lg:flex" :style="accentStyle">
            <Icon :icon="meta.icon" class="size-6" />
        </span>
        <div class="min-w-0">
            <Link v-if="parent" :href="parent.href" class="text-eyebrow uppercase transition-colors hover:text-accent-500 focus-visible:text-accent-500" :style="accentStyle">{{ parent.label }}</Link>
            <h1 class="mt-1 font-display text-display">{{ title }}</h1>
            <p v-if="subtitle" class="mt-2 text-meta text-neutral-500">{{ subtitle }}</p>
            <Link
                v-if="tagLink"
                :href="tagLink.url"
                class="mt-3 inline-flex items-center gap-1 text-meta text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900"
            >
                See everything tagged {{ tagLink.name }}
                <Icon name="ArrowRight01Icon" class="size-4" />
            </Link>
        </div>
    </header>

    <template v-if="type === 'flight' && map.length">
        <FlightsMap :routes="map" class="mt-8" />
        <Link href="/flights/map" class="mt-3 inline-flex items-center gap-1 text-meta text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900">
            View on the globe
            <Icon name="ArrowRight01Icon" class="size-4" />
        </Link>
    </template>
    <StationsMap v-else-if="type === 'fuel' && map.length" :stations="map" class="mt-8" />
    <PlacesMap v-else-if="type === 'checkin' && map.length" :places="map" class="mt-8" />

    <TaxonomyFilter :chips="chips" />

    <div v-if="groups.length" class="mt-10 flex flex-col gap-14">
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
        />
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>

    <Pagination
        v-if="lastPage > 1"
        class="mt-14"
        :current-page="currentPage"
        :last-page="lastPage"
        :prev-url="prevUrl"
        :next-url="nextUrl"
    />
</template>
