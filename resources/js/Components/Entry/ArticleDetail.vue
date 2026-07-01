<script setup>
/**
 * ArticleDetail -- renders the body and metadata for an article entry.
 *
 * All articles are now served from Statamic, so `entry.bodyHtml` is always
 * a pre-rendered HTML string (Bard -> ProseBody).
 */
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';
import ProseBody from '../Ui/ProseBody.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

/** Tag list -- normalised to an array regardless of the source shape. */
const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.draft || tags.length" class="flex flex-wrap gap-2">
            <Pill v-if="entry.draft" label="Draft" variant="accent" />
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <p v-if="entry.excerpt" class="text-body text-lg text-neutral-700">{{ entry.excerpt }}</p>

        <ProseBody :html="entry.bodyHtml" />
    </div>
</template>
