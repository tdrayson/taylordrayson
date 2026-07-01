<script setup>
/**
 * NoteDetail -- renders the body of a short-form note entry.
 *
 * When the entry originates from Statamic, `entry.bodyHtml` is a pre-rendered
 * HTML string (Bard -> ProseBody). When it originates from the legacy Eloquent
 * path, `entry.content` is an Editor.js document (BlockContent). The component
 * handles both shapes: bodyHtml takes precedence when present.
 */
import { computed } from 'vue';
import BlockContent from '../Ui/BlockContent.vue';
import ProseBody from '../Ui/ProseBody.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

/** True when the entry was rendered server-side via Statamic's Bard pipeline. */
const hasStatamicContent = computed(() => typeof props.entry.bodyHtml === 'string');
</script>

<template>
    <div>
        <ProseBody v-if="hasStatamicContent" :html="entry.bodyHtml" />
        <BlockContent v-else :document="entry.content" />
    </div>
</template>
