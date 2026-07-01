<script setup>
/**
 * ArticleDetail -- renders the body and metadata for an article entry.
 *
 * When the entry originates from Statamic, `entry.bodyHtml` is a pre-rendered
 * HTML string (Bard -> ProseBody). When it originates from the legacy Eloquent
 * path, `entry.content` is an Editor.js document (BlockContent). The component
 * handles both shapes: bodyHtml takes precedence when present.
 */
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';
import BlockContent from '../Ui/BlockContent.vue';
import ProseBody from '../Ui/ProseBody.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

/** Tag list -- normalised to an array regardless of the source shape. */
const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));

/** True when the entry was rendered server-side via Statamic's Bard pipeline. */
const hasStatamicContent = computed(() => typeof props.entry.bodyHtml === 'string');
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.draft || tags.length" class="flex flex-wrap gap-2">
            <Pill v-if="entry.draft" label="Draft" variant="accent" />
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <p v-if="entry.excerpt" class="text-body text-lg text-neutral-700">{{ entry.excerpt }}</p>

        <ProseBody v-if="hasStatamicContent" :html="entry.bodyHtml" />
        <BlockContent v-else :document="entry.content" />
    </div>
</template>
