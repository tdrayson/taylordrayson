<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps, usePage } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import EntryMap from '../Components/Maps/EntryMap.vue';
import Conversation from '../Components/Conversation/Conversation.vue';
import EntryFooter from '../Components/Entry/EntryFooter.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';
import PasswordPrompt from '../Components/Entry/PasswordPrompt.vue';
import { entryType } from '../entryTypes.js';
import { useTokenText } from '../composables/useTokenText';

import EntryEditor from '../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../lib/editor/defaults.js';
import { provideLinkContext } from '../lib/linkContext.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    type: { type: String, required: true },
    accent: { type: String, required: true },
    // Null for title-less types (notes): the header shows only the type label and date.
    title: { type: String, default: null },
    // The title as tokens when it carries a measurement, e.g. a night's sleep.
    titleTokens: { type: Array, default: null },
    occurredAt: { type: String, default: null },
    dayUrl: { type: String, default: null },
    // { title, url } when this entry falls inside a trip window, else null.
    trip: { type: Object, default: null },
    entry: { type: Object, default: null },
    polyline: { type: String, default: null },
    source: { type: Object, default: null },
    // Grouped display lines, the entry's own direct tags, and subjects its
    // prose mentions but hasn't tagged; empty for a model with no subjects
    // support (a day of food, an aggregate).
    subjects: { type: Object, default: () => ({ lines: [], direct: [], mentioned: [] }) },
    og: { type: Object, default: () => ({}) },
    // One ConversationData, server-rendered so the responses read without JS.
    conversation: { type: Object, default: null },
    occurredLabel: { type: String, default: '' },
    occurredOffset: { type: String, default: '' },
    // Map of href -> preview data for internal content links; only ArticleDetail
    // consumes it, so it's bound conditionally below rather than on every type.
    linkPreviews: { type: Object, default: () => ({}) },
    linkFavicons: { type: Object, default: () => ({}) },
    media: { type: Object, default: () => ({}) },
    // Editing in place: a synced type's fields are just its status.
    editing: { type: Boolean, default: false },
    editAction: { type: String, default: null },
    fields: { type: Array, default: () => [] },
    // The owner's only, so the editor can show the password it is locked with.
    password: { type: String, default: null },
    locked: { type: Boolean, default: false },
    unlockUrl: { type: String, default: null },
    // [{ extension, type, label, url }] this entry can be exported as.
    formats: { type: Array, default: () => [] },
});

const { tokenText, tokenTitle } = useTokenText();
const titleText = computed(() => (props.titleTokens ? tokenText(props.titleTokens) : props.title));
const titleExact = computed(() => tokenTitle(props.titleTokens));

const signedIn = computed(() => usePage().props.signedIn === true);

// This entry's own URL, for the u-url a parser needs on a permalink.
const permalink = computed(() => usePage().url);

provideLinkContext(computed(() => ({ previews: props.linkPreviews, favicons: props.linkFavicons })));

// Current values for the form, read off the entry payload. Dotted field names
// address into meta, which is where a book keeps its author.
// Media lives in collections, not columns, so it arrives beside the entry
// rather than on it.
const editorValues = computed(() => valuesFor(props.fields, {
    ...(props.entry ?? {}),
    ...props.media,
    password: props.password,
    // The payload carries {name, slug, url} so the footer can link each tag; the
    // form posts names, which is what syncTagNames takes.
    tags: (props.entry?.tags ?? []).map((tag) => tag.name),
}));

// Every *Detail.vue, eagerly bundled as the static imports were. A type's detail is
// found by convention: 'place' renders PlaceDetail, 'this-week-with' ThisWeekWithDetail.
const detailModules = import.meta.glob('../Components/Entry/*Detail.vue', { eager: true, import: 'default' });

// Turn a type key into its component file name, e.g. 'this-week-with' -> 'ThisWeekWith'.
function studly(key) {
    return key.split(/[-_]/).map((part) => part.charAt(0).toUpperCase() + part.slice(1)).join('');
}

const meta = computed(() => entryType(props.type));
const detailComponent = computed(() => detailModules[`../Components/Entry/${studly(props.type)}Detail.vue`] ?? null);
const accentStyle = computed(() => ({ color: `var(--color-${props.accent})` }));

// Linkable tags for the shared footer; only taggable types carry the key.
const tags = computed(() => (Array.isArray(props.entry?.tags) ? props.entry.tags : []));

// Title-less entries (notes) render no visible headline, but the page still
// needs exactly one h1 for the outline: fall back to the type + date, hidden
// visually (mirrors Timeline.vue's sr-only "Taylor Drayson timeline" h1).
const fullOccurredLabel = computed(() => [props.occurredLabel, props.occurredOffset].filter(Boolean).join(' '));

// Aggregate / one-per-day types have a generic slug and a stat-style title, so the
// type label reads better in the breadcrumb. Everything else uses its title.
const SINGULAR_TYPES = ['sleep', 'food', 'fuel', 'note'];
const crumbLabel = computed(() => (SINGULAR_TYPES.includes(props.type) ? meta.value.label : props.title));

// Dated entries crumb through their year, month and day; an undated draft leads back to /drafts.
function breadcrumb() {
    if (! props.dayUrl) {
        return [{ label: 'Drafts', href: '/drafts' }, { label: crumbLabel.value ?? meta.value.label }];
    }

    const [, year, month, day] = props.dayUrl.split('/');
    const monthName = new Date(props.occurredAt).toLocaleDateString('en-GB', { month: 'long' });

    return [
        { label: year, href: `/${year}` },
        { label: monthName, href: `/${year}/${month}` },
        { label: String(Number(day)), href: props.dayUrl },
        { label: crumbLabel.value },
    ];
}

setLayoutProps({ minimal: props.editing, breadcrumb: breadcrumb() });
</script>

<template>
    <AppHead :og="og" :formats="formats" />

    <!-- Editing replaces the entry rather than sitting under it: the editor
         draws its own title and body, so showing both repeats them. Same split
         as Page.vue. -->
    <EntryEditor
        v-if="editing"
        :fields="fields"
        :values="editorValues"
        :action="editAction"
        :heading="title ?? meta.label"
    />

    <!-- Spans the page and re-establishes the grid, as Page.vue does, so a
         child can opt into breakout or full width. Children with no column
         of their own still default to `content`. -->
    <article v-else class="h-entry full-width content-grid">
        <header class="relative">
            <div class="min-w-0">
                <div class="relative">
                    <span class="absolute -left-16 top-1/2 hidden size-12 -translate-y-1/2 shrink-0 items-center justify-center rounded-full bg-neutral-25 lg:flex" :style="accentStyle">
                        <Icon :icon="meta.icon" class="size-6" />
                    </span>
                    <Link :href="meta.href" class="text-2xs font-semibold uppercase tracking-wider underline-offset-4 hover:underline focus-visible:underline" :style="accentStyle">{{ meta.label }}</Link>
                </div>
                <!-- Universal headline measure across every entry type, matching StoryChapter's heading. -->
                <h1 v-if="title" v-twemoji :title="titleExact" class="mt-1 max-w-2xl p-name font-display text-5xl font-extrabold tracking-tight">{{ titleText }}</h1>
                <!-- No p-name: a title-less type is a note, and mf2 readers tell
                     a note from an article by the absence of a name separate
                     from the content. This heading is for the outline only. -->
                <h1 v-else class="sr-only">{{ meta.label }}, {{ fullOccurredLabel }}</h1>
                <Link v-if="dayUrl" :href="dayUrl" class="mt-2 inline-block text-sm font-medium text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500">
                    <time :datetime="occurredAt" class="dt-published">{{ occurredLabel }} {{ occurredOffset }}</time>
                </Link>
                <p v-if="trip" class="mt-1 text-sm text-neutral-500">
                    Part of
                    <Link :href="trip.url" class="font-medium text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ trip.title }}</Link>
                </p>
            </div>
            <!-- Hidden, not dropped: a parser needs this entry's own URL and its
                 author, and neither has anywhere to sit in the visible design. -->
            <a class="u-url u-uid" :href="permalink" hidden>{{ title ?? meta.label }}</a>
            <AuthorRef />
        </header>

        <PasswordPrompt v-if="locked" :action="unlockUrl" class="mt-10" />

        <EntryMap v-if="polyline && type !== 'activity'" :polyline="polyline" :color="`var(--color-${accent})`" class="mt-8" />

        <component
            v-if="detailComponent && entry"
            :is="detailComponent"
            :entry="entry"
            class="mt-10"
        />

        <Link v-if="signedIn && editAction" :href="`?edit`" class="mt-6 inline-block text-sm text-accent-500 underline underline-offset-2 transition-colors hover:text-accent-700">Edit this entry</Link>

        <EntryFooter
            :source="source"
            :tags="tags"
            :formats="formats"
            :subjects="subjects"
            :type="type"
            :entry-id="entry?.id"
            :signed-in="signedIn"
            class="mt-10"
        />

        <Conversation v-if="conversation" :conversation="conversation" :og="og" class="mt-12" />
    </article>
</template>
