<script setup>
import { computed, ref } from 'vue';
import { setLayoutProps, usePage, Link, router } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SubjectImage from '../../Components/Subjects/SubjectImage.vue';
import SubjectFacts from '../../Components/Subjects/SubjectFacts.vue';
import BlockContent from '../../Components/Ui/BlockContent.vue';
import Button from '../../Components/Ui/Button.vue';
import PhotoGrid from '../../Components/Ui/PhotoGrid.vue';
import Pagination from '../../Components/Ui/Pagination.vue';
import SectionHead from '../../Components/Ui/SectionHead.vue';
import Lightbox from '../../Components/Overlays/Lightbox.vue';
import LocationMap from '../../Components/Maps/LocationMap.vue';
import DateGroup from '../../Components/Timeline/DateGroup.vue';
import EntryEditor from '../../Components/Editor/EntryEditor.vue';
import { valuesFor } from '../../lib/editor/defaults.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // SubjectData::toArray() shape.
    subject: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    photos: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ entries: {}, photos: 0, first: null, last: null }) },
    companions: { type: Array, default: () => [] },
    editing: { type: Boolean, default: false },
    // Field definitions from FieldRegistry, driving the properties panel.
    fields: { type: Array, default: () => [] },
    // The record's own value per offered field, for the editor to start from.
    values: { type: Object, default: () => ({}) },
    og: { type: Object, default: () => ({}) },
});

// The URL is /life/{segment}/{slug}; the segment isn't otherwise on the
// payload, and the breadcrumb needs it to link back to the kind index.
const segment = computed(() => props.subject.url.split('/')[2]);
const hasLocation = computed(() => props.subject.latitude !== null && props.subject.longitude !== null);
// The URL word is already the lowercased plural of the kind, so the crumb
// takes it from there rather than re-pluralising the singular display label.
const kindPlural = computed(() => segment.value.charAt(0).toUpperCase() + segment.value.slice(1));

const totalEntries = computed(() => Object.values(props.stats.entries).reduce((sum, count) => sum + count, 0));

setLayoutProps({
    minimal: props.editing,
    breadcrumb: [
        { label: 'Life', href: '/life' },
        { label: kindPlural.value, href: `/life/${segment.value}` },
        { label: props.subject.name },
    ],
});

const signedIn = computed(() => usePage().props.signedIn === true);

// The feed can run to hundreds of days; the photos above it cannot, so only
// this half pages.
const pageUrl = (page) => (page <= 1 ? props.subject.url : `${props.subject.url}?page=${page}`);
const prevUrl = computed(() => (props.currentPage > 1 ? pageUrl(props.currentPage - 1) : null));
const nextUrl = computed(() => (props.currentPage < props.lastPage ? pageUrl(props.currentPage + 1) : null));

// Straight off the record, not rebuilt from the display props: listing the
// keys by hand meant any field not on that list opened empty and was saved
// back empty.
const editorValues = computed(() => valuesFor(props.fields, props.values));

/** Names what goes with the subject, so a confirm cannot be clicked blind. */
const deleteMessage = computed(() => {
    const parts = [];

    if (totalEntries.value) {
        parts.push(`${totalEntries.value} ${totalEntries.value === 1 ? 'entry' : 'entries'}`);
    }

    if (props.stats.photos) {
        parts.push(`${props.stats.photos} tagged ${props.stats.photos === 1 ? 'photo' : 'photos'}`);
    }

    const links = parts.length ? ` It will be unlinked from ${parts.join(' and ')}, which are kept.` : '';

    return `Delete ${props.subject.name}?${links}`;
});

function destroy() {
    if (window.confirm(deleteMessage.value)) {
        router.delete(`/subjects/${props.subject.id}`);
    }
}

const lightboxIndex = ref(null);
</script>

<template>
    <AppHead :og="og" />

    <div v-if="editing" class="w-full max-w-2xl">
        <EntryEditor
            :fields="fields"
            :values="editorValues"
            :action="`/subjects/${subject.id}`"
            submit-label="Save"
        />
    </div>

    <!-- Re-establishes the grid, as Entry does, so the establishing image can
         break out past the reading column while the prose stays in it. -->
    <article v-else class="h-card full-width content-grid">
        <header>
            <h1 v-twemoji class="p-name max-w-2xl font-display text-display">{{ subject.name }}</h1>
            <p v-if="subject.category" class="mt-2 text-meta text-neutral-500">{{ subject.category }}</p>

            <!-- Both only mean anything to the owner, so they sit with the
                 name rather than interrupting the sections below. -->
            <div v-if="signedIn" class="mt-3 flex items-center gap-4">
                <Button href="?edit" variant="link" size="inline">Edit</Button>
                <Button variant="link" size="inline" class="text-red-600 hover:text-red-700" @click="destroy">Delete</Button>
            </div>
        </header>

        <SubjectImage
            v-if="subject.cover"
            :cover="subject.cover"
            :name="subject.name"
            :kind="subject.kind"
            hero
            class="wide mt-8"
        />

        <!-- Hidden, not dropped: microformats parsers read the DOM and ignore
             CSS, on the same reasoning as ProfileCard's rel-me links. The
             page's own u-url is a self-link, and each identity that carries a
             real URL adds another. -->
        <a :href="subject.url" class="u-url" hidden>{{ subject.name }}</a>
        <a
            v-for="identity in subject.identityLinks"
            :key="identity.platform"
            :href="identity.url"
            rel="me"
            class="u-url"
            hidden
        >{{ identity.platform }}</a>

        <!-- Facts alongside the prose rather than under it: they read as a
             sidebar of reference detail, and a subject with a dozen of them no
             longer pushes everything below it down the page. -->
        <div class="mt-8 grid gap-8 sm:grid-cols-3 sm:items-start sm:gap-16">
            <div class="min-w-0 sm:col-span-2">
                <BlockContent v-if="subject.bio" :document="subject.bio" />
            </div>

            <SubjectFacts :facts="subject.facts" :rows="stats.rows" />
        </div>

        <LocationMap
            v-if="hasLocation"
            :lat="subject.latitude"
            :lng="subject.longitude"
            :label="subject.name"
            class="mt-8"
        />

        <section v-if="companions.length">
            <SectionHead title="Appears with" />
            <div class="flex flex-wrap gap-4">
                <Link
                    v-for="companion in companions"
                    :key="companion.url"
                    :href="companion.url"
                    class="group flex items-center gap-2 rounded-full py-1 pr-3 transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                >
                    <span class="size-8 overflow-hidden rounded-full bg-neutral-25">
                        <img v-if="companion.cover" :src="companion.cover.src" :alt="companion.cover.alt || companion.name" class="size-full object-cover">
                    </span>
                    <span class="text-meta font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ companion.name }}</span>
                </Link>
            </div>
        </section>

        <section v-if="photos.length">
            <SectionHead title="Photos" />
            <PhotoGrid :photos="photos" @open="lightboxIndex = $event" />
        </section>

        <section v-if="groups.length" class="mt-12">
            <!-- A run of dated entries needs no label to say so. -->
            <h2 class="sr-only">Timeline</h2>
            <div class="flex flex-col gap-14">
                <DateGroup
                    v-for="group in groups"
                    :key="group.label"
                    :label="group.label"
                    :date="group.date"
                    :href="group.href"
                    :items="group.items"
                    :heading-level="3"
                />
            </div>

            <Pagination
                v-if="lastPage > 1"
                class="mt-12"
                :current-page="currentPage"
                :last-page="lastPage"
                :prev-url="prevUrl"
                :next-url="nextUrl"
            />
        </section>

        <Lightbox v-model:index="lightboxIndex" :photos="photos" />
    </article>
</template>
