<script setup>
/**
 * ProseBody -- renders server-rendered (Bard) HTML inside the prose type scale.
 *
 * @prop {string} html - The sanitized HTML string produced by Statamic's Bard
 *   augmentation pipeline (PageController -> ContentEntry::bodyHtml).
 */
defineProps({
    html: { type: String, default: '' },
});
</script>

<template>
    <div class="prose-body" v-html="html" />
</template>
