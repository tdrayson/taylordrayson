<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { setLayoutProps } from '../composables/useLayout.js';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import Pagination from '../Components/Ui/Pagination.vue';
import FlightsMap from '../Components/Maps/FlightsMap.vue';
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
        <span class="absolute left-0 top-0 hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 sm:flex lg:-left-16" :style="accentStyle">
            <Icon :icon="meta.icon" class="size-6" />
        </span>
        <div class="min-w-0 sm:pl-16 lg:pl-0">
            <Link v-if="parent" :href="parent.href" class="text-eyebrow uppercase transition-colors hover:text-accent-500 focus-visible:text-accent-500" :style="accentStyle">{{ parent.label }}</Link>
            <h1 class="mt-1 font-display text-display">{{ title }}</h1>
            <p v-if="subtitle" class="mt-2 text-meta text-neutral-500">{{ subtitle }}</p>
        </div>
    </header>

    <FlightsMap v-if="map.length" :routes="map" class="mt-8" />

    <div v-if="chips.length" class="mt-6 flex flex-wrap gap-2">
        <Link
            v-for="chip in chips"
            :key="chip.href"
            :href="chip.href"
            class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-caption font-medium transition-colors"
            :class="chip.active
                ? 'bg-accent-500 text-neutral-0'
                : 'bg-neutral-25 text-neutral-700 hover:bg-accent-50 hover:text-accent-700'"
        >
            <img v-if="chip.icon" :src="chip.icon" alt="" class="size-4 shrink-0 object-contain">
            {{ chip.label }}
        </Link>
    </div>

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
