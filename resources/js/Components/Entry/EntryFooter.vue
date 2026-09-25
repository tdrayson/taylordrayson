<script setup>
import { Link } from '@inertiajs/vue3';
import Source from '../Profile/Source.vue';
import SubjectChips from '../Subjects/SubjectChips.vue';

const props = defineProps({
    // The data source, e.g. { platform: 'swarm', url }, or null for first-party entries.
    source: { type: Object, default: null },
    // Linkable tags [{ name, slug, url }]; only taggable types (notes, articles, projects, events) carry any.
    tags: { type: Array, default: () => [] },
    // Grouped subject lines, the entry's own direct tags, and mentioned-but-
    // untagged subjects; see SubjectChips.
    subjects: { type: Object, default: () => ({ lines: [], direct: [], mentioned: [] }) },
    type: { type: String, default: null },
    entryId: { type: [Number, String], default: null },
    signedIn: { type: Boolean, default: false },
    // [{ extension, type, label, url }] this entry can be exported as.
    formats: { type: Array, default: () => [] },
});

// The whole block collapses when an entry has nothing to put in it, so an
// untaggable first-party entry with no formats renders no empty rule. Signed
// in, it still shows so the "+" to tag a subject stays reachable.
const hasContent = () => props.tags.length > 0 || Boolean(props.source) || props.formats.length > 0 || props.subjects.lines.length > 0 || props.signedIn;
</script>

<template>
    <!-- Tags, source and formats are independent lines, so a type gets whichever
         it has. No rule: the entry's metadata and the responses under it are one
         quiet block, separated from the entry by space rather than by a line. -->
    <div v-if="hasContent()" class="space-y-2">
        <SubjectChips
            v-if="type && entryId"
            :lines="subjects.lines"
            :direct="subjects.direct"
            :mentioned="subjects.mentioned"
            :type="type"
            :entry-id="entryId"
            :signed-in="signedIn"
        />

        <p v-if="tags.length" class="text-xs text-neutral-500">
            Tagged
            <template v-for="(tag, index) in tags" :key="tag.slug"><Link :href="tag.url" class="p-category font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ tag.name }}</Link><span v-if="index < tags.length - 1">, </span></template>
        </p>

        <Source v-if="source" :platform="source.platform" :url="source.url" />

        <!-- Plain anchors, not <Link>: Inertia's router would try to parse a
             .json response as a page. -->
        <p v-if="formats.length" class="text-xs text-neutral-500">
            View as
            <template v-for="(format, index) in formats" :key="format.extension"><a :href="format.url" class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">.{{ format.extension }}</a><span v-if="index < formats.length - 1">, </span></template>
        </p>
    </div>
</template>
