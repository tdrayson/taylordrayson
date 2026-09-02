<script setup>
import { computed, ref } from 'vue';
import { setLayoutProps, Link } from '@inertiajs/vue3';
import AppHead from '../../Components/AppHead.vue';
import AppLayout from '../../Layouts/AppLayout.vue';
import SubjectImage from '../../Components/Subjects/SubjectImage.vue';
import SubjectFacts from '../../Components/Subjects/SubjectFacts.vue';
import BlockContent from '../../Components/Ui/BlockContent.vue';
import PhotoGrid from '../../Components/Ui/PhotoGrid.vue';
import SectionHead from '../../Components/Ui/SectionHead.vue';
import Lightbox from '../../Components/Overlays/Lightbox.vue';
import LocationMap from '../../Components/Maps/LocationMap.vue';
import DateGroup from '../../Components/Timeline/DateGroup.vue';
import StatGrid from '../../Components/Stats/StatGrid.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // SubjectData::toArray() shape.
    subject: { type: Object, required: true },
    groups: { type: Array, default: () => [] },
    photos: { type: Array, default: () => [] },
    stats: { type: Object, default: () => ({ entries: {}, photos: 0, first: null, last: null }) },
    companions: { type: Array, default: () => [] },
    og: { type: Object, default: () => ({}) },
});

// The URL is /life/{segment}/{slug}; the segment isn't otherwise on the
// payload, and the breadcrumb needs it to link back to the kind index.
const segment = computed(() => props.subject.url.split('/')[2]);
const isThing = computed(() => props.subject.kind === 'Thing');
const hasLocation = computed(() => props.subject.latitude !== null && props.subject.longitude !== null);
const eyebrow = computed(() => props.subject.category ?? props.subject.kind);

const totalEntries = computed(() => Object.values(props.stats.entries).reduce((sum, count) => sum + count, 0));
const statItems = computed(() => [
    { label: 'Entries', value: totalEntries.value },
    { label: 'Photos', value: props.stats.photos },
]);

setLayoutProps({
    breadcrumb: [
        { label: 'Life', href: '/life' },
        { label: `${eyebrow.value}s`, href: `/life/${segment.value}` },
        { label: props.subject.name },
    ],
});

const lightboxIndex = ref(null);
</script>

<template>
    <AppHead :og="og" />

    <article>
        <SubjectImage
            :cover="subject.cover"
            :name="subject.name"
            :kind="subject.kind"
            :wide="isThing"
            class="mb-6"
            :class="isThing ? '' : 'max-w-md'"
        />

        <header>
            <p class="text-eyebrow uppercase text-neutral-500">{{ eyebrow }}</p>
            <h1 v-twemoji class="mt-1 max-w-2xl font-display text-display">{{ subject.name }}</h1>
        </header>

        <BlockContent v-if="subject.bio" :document="subject.bio" class="mt-8" />

        <SubjectFacts :facts="subject.facts" class="mt-8" />

        <StatGrid :stats="statItems" size="sm" class="mt-8" />
        <p v-if="stats.first && stats.last" class="mt-2 text-meta text-neutral-500">
            <template v-if="stats.first === stats.last">Logged on {{ stats.first }}</template>
            <template v-else>From {{ stats.first }} to {{ stats.last }}</template>
        </p>

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
                        <img v-if="companion.cover" :src="companion.cover.src" alt="" class="size-full object-cover">
                    </span>
                    <span class="text-meta font-medium text-neutral-900 underline-offset-4 group-hover:underline group-focus-visible:underline">{{ companion.name }}</span>
                </Link>
            </div>
        </section>

        <section v-if="groups.length">
            <SectionHead title="Timeline" />
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
        </section>

        <section v-if="photos.length">
            <SectionHead title="Photos" />
            <PhotoGrid :photos="photos" @open="lightboxIndex = $event" />
        </section>

        <Lightbox v-model:index="lightboxIndex" :photos="photos" />
    </article>
</template>
