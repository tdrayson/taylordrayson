<script setup>
/**
 * NoteDetail -- renders the body of a short-form note entry.
 *
 * All notes are now served from Statamic, so `entry.bodyHtml` is always
 * a pre-rendered HTML string (Bard -> ProseBody).
 */
import ProseBody from '../Ui/ProseBody.vue';

defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <div>
        <ProseBody :html="entry.bodyHtml" />
    </div>
</template>
