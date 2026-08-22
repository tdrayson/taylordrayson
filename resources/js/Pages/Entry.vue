<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import EntryMap from '../Components/Maps/EntryMap.vue';
import EntryFooter from '../Components/Entry/EntryFooter.vue';
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
import EntryEditor from '../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../lib/editor/defaults.js';
import { provideLinkContext } from '../lib/linkContext.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    type: { type: String, required: true },
    accent: { type: String, required: true },
    // Null for title-less types (notes): the header shows only the type label and date.
    title: { type: String, default: null },
    occurredAt: { type: String, required: true },
    dayUrl: { type: String, required: true },
    // { title, url } when this entry falls inside a trip window, else null.
    trip: { type: Object, default: null },
    entry: { type: Object, required: true },
    polyline: { type: String, default: null },
    source: { type: Object, default: null },
    og: { type: Object, default: () => ({}) },
    occurredLabel: { type: String, default: '' },
    occurredOffset: { type: String, default: '' },
    // Map of href -> preview data for internal content links; only ArticleDetail
    // consumes it, so it's bound conditionally below rather than on every type.
    linkPreviews: { type: Object, default: () => ({}) },
    linkFavicons: { type: Object, default: () => ({}) },
    media: { type: Object, default: () => ({}) },
    // kind:id -> resolved mention, for content that carries any.
    mentions: { type: Object, default: () => ({}) },
    // Editing in place: only hand-authored types get a form at all.
    editing: { type: Boolean, default: false },
    editType: { type: String, default: null },
    fields: { type: Array, default: () => [] },
});

const signedIn = computed(() => usePage().props.signedIn === true);

provideLinkContext(computed(() => ({ previews: props.linkPreviews, favicons: props.linkFavicons })));

// Current values for the form, read off the entry payload. Dotted field names
// address into meta, which is where a book keeps its author.
// Media lives in collections, not columns, so it arrives beside the entry
// rather than on it.
const editorValues = computed(() => valuesFor(props.fields, { ...props.entry, ...props.media }));

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

// Linkable tags for the shared footer; only taggable types carry the key.
const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));

const [, year, month, day] = props.dayUrl.split('/');
const monthName = computed(() => new Date(props.occurredAt).toLocaleDateString('en-GB', { month: 'long' }));

// Title-less entries (notes) render no visible headline, but the page still
// needs exactly one h1 for the outline: fall back to the type + date, hidden
// visually (mirrors Timeline.vue's sr-only "Taylor Drayson timeline" h1).
const fullOccurredLabel = computed(() => `${props.occurredLabel} ${props.occurredOffset}`.trim());

// Aggregate / one-per-day types have a generic slug and a stat-style title, so the
// type label reads better in the breadcrumb. Everything else uses its title.
const SINGULAR_TYPES = ['sleep', 'calorie', 'fuel', 'note'];
// A media hero shows the studio's own title logo, so the heading stays for the
// outline but steps out of the way rather than printing the title twice.
const titleInHero = computed(() => Boolean(props.entry?.logo && props.entry?.logoIsTitle));

const crumbLabel = computed(() => (SINGULAR_TYPES.includes(props.type) ? meta.value.label : props.title));

setLayoutProps({
    minimal: props.editing,
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

    <!-- Editing replaces the entry rather than sitting under it: the editor
         draws its own title and body, so showing both repeats them. Same split
         as Page.vue. -->
    <EntryEditor
        v-if="editing"
        :fields="fields"
        :values="editorValues"
        :action="`/entries/${editType}/${entry.id}`"
    />

    <header v-else class="relative">
        <div class="min-w-0">
            <div class="relative">
                <span class="absolute -left-16 top-1/2 hidden size-12 -translate-y-1/2 shrink-0 items-center justify-center rounded-full bg-neutral-25 lg:flex" :style="accentStyle">
                    <Icon :icon="meta.icon" class="size-6" />
                </span>
                <Link :href="meta.href" class="text-eyebrow uppercase underline-offset-4 hover:underline focus-visible:underline" :style="accentStyle">{{ meta.label }}</Link>
            </div>
            <!-- Universal headline measure across every entry type, matching StoryChapter's heading. -->
            <h1 v-if="title" v-twemoji :class="titleInHero ? 'sr-only' : 'mt-1 max-w-2xl font-display text-display'">{{ title }}</h1>
            <h1 v-else class="sr-only">{{ meta.label }}, {{ fullOccurredLabel }}</h1>
            <Link :href="dayUrl" class="mt-2 inline-block text-meta font-medium text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500">
                <time :datetime="occurredAt">{{ occurredLabel }} {{ occurredOffset }}</time>
            </Link>
            <p v-if="trip" class="mt-1 text-meta text-neutral-500">
                Part of
                <Link :href="trip.url" class="font-medium text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ trip.title }}</Link>
            </p>
        </div>
    </header>

    <EntryMap v-if="! editing && polyline && type !== 'activity'" :polyline="polyline" :color="`var(--color-${accent})`" class="mt-8" />

    <component
        v-if="! editing && detailComponent"
        :is="detailComponent"
        :entry="entry"
        class="mt-10"
    />

    <p v-if="! editing && signedIn && editType" class="mt-6">
        <Link :href="`?edit`" class="text-meta text-accent-500 underline underline-offset-2">Edit this entry</Link>
    </p>

    <EntryFooter v-if="! editing" :source="source" :tags="tags" class="mt-10" />
</template>
