<script setup>
import BlockContent from '../Ui/BlockContent.vue';

defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <BlockContent :document="entry.content" />
</template>
