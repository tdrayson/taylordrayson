<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Button from '../Components/Ui/Button.vue';
import Icon from '../Components/Ui/Icon.vue';
import EntryMap from '../Components/Maps/EntryMap.vue';
import Source from '../Components/Profile/Source.vue';
import { entryType } from '../entryTypes.js';

import ActivityDetail from '../Components/Entry/ActivityDetail.vue';
import SleepDetail from '../Components/Entry/SleepDetail.vue';
import CalorieDetail from '../Components/Entry/CalorieDetail.vue';
import MediaDetail from '../Components/Entry/MediaDetail.vue';
import EventDetail from '../Components/Entry/EventDetail.vue';
import AppearanceDetail from '../Components/Entry/AppearanceDetail.vue';
import PodcastDetail from '../Components/Entry/PodcastDetail.vue';
import FlightDetail from '../Components/Entry/FlightDetail.vue';
import CheckinDetail from '../Components/Entry/CheckinDetail.vue';
import FuelDetail from '../Components/Entry/FuelDetail.vue';
import ProjectDetail from '../Components/Entry/ProjectDetail.vue';
import ArticleDetail from '../Components/Entry/ArticleDetail.vue';
import NoteDetail from '../Components/Entry/NoteDetail.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    type: { type: String, required: true },
    accent: { type: String, required: true },
    title: { type: String, required: true },
    occurredAt: { type: String, required: true },
    dayUrl: { type: String, required: true },
    entry: { type: Object, required: true },
    polyline: { type: String, default: null },
    source: { type: Object, default: null },
    og: { type: Object, default: () => ({}) },
    editUrl: { type: String, default: null },
    occurredLabel: { type: String, default: '' },
    occurredOffset: { type: String, default: '' },
});

const pageProps = usePage();
const canEdit = computed(() => !!pageProps.props.auth?.user && !!props.editUrl);

const DETAIL_COMPONENTS = {
    activity: ActivityDetail,
    sleep: SleepDetail,
    calorie: CalorieDetail,
    media: MediaDetail,
    event: EventDetail,
    appearance: AppearanceDetail,
    podcast: PodcastDetail,
    flight: FlightDetail,
    checkin: CheckinDetail,
    fuel: FuelDetail,
    project: ProjectDetail,
    article: ArticleDetail,
    note: NoteDetail,
};

const meta = computed(() => entryType(props.type));
const detailComponent = computed(() => DETAIL_COMPONENTS[props.type] ?? null);
const accentStyle = computed(() => ({ color: `var(--color-${props.accent})` }));
const [, year, month, day] = props.dayUrl.split('/');
const monthName = computed(() => new Date(props.occurredAt).toLocaleDateString('en-GB', { month: 'long' }));

// Aggregate / one-per-day types have a generic slug and a stat-style title, so the
// type label reads better in the breadcrumb. Everything else uses its title.
const SINGULAR_TYPES = ['sleep', 'calorie', 'fuel', 'note'];
const crumbLabel = computed(() => (SINGULAR_TYPES.includes(props.type) ? meta.value.label : props.title));

setLayoutProps({
    breadcrumb: [
        { label: year, href: `/${year}` },
        { label: monthName.value, href: `/${year}/${month}` },
        { label: String(Number(day)), href: props.dayUrl },
        { label: crumbLabel.value },
    ],
});
</script>

<template>
    <AppHead :og="og" />

    <header class="flex items-start gap-4">
        <span class="hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 sm:flex" :style="accentStyle">
            <Icon :icon="meta.icon" class="size-6" />
        </span>
        <div class="min-w-0">
            <Link :href="meta.href" class="text-eyebrow uppercase underline-offset-4 hover:underline" :style="accentStyle">{{ meta.label }}</Link>
            <h1 class="mt-1 font-display text-display">{{ title }}</h1>
            <Link :href="dayUrl" class="mt-2 inline-block text-meta font-medium text-neutral-700 transition-colors hover:text-accent-500">
                <time :datetime="occurredAt">{{ occurredLabel }} {{ occurredOffset }}</time>
            </Link>
            <Button v-if="canEdit" :href="editUrl" variant="secondary" size="sm" class="mt-3">Edit</Button>
        </div>
    </header>

    <EntryMap v-if="polyline" :polyline="polyline" :color="`var(--color-${accent})`" class="mt-8" />

    <component :is="detailComponent" v-if="detailComponent" :entry="entry" class="mt-10" />

    <Source v-if="source" :platform="source.platform" :url="source.url" class="mt-10" />
</template>
