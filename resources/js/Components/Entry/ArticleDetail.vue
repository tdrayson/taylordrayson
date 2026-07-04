<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';
import BlockContent from '../Ui/BlockContent.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.draft || tags.length" class="flex flex-wrap gap-2">
            <Pill v-if="entry.draft" label="Draft" variant="accent" />
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <p v-if="entry.excerpt" class="max-w-reading text-body text-lg text-neutral-700">{{ entry.excerpt }}</p>

        <BlockContent :document="entry.content" />
    </div>
</template>
