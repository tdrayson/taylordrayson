<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));
</script>

<template>
    <div class="space-y-8">
        <div v-if="entry.draft || tags.length" class="flex flex-wrap gap-2">
            <Pill v-if="entry.draft" label="Draft" accent />
            <Pill v-for="tag in tags" :key="tag" :label="tag" />
        </div>

        <p v-if="entry.excerpt" class="text-body text-lg text-ink-2">{{ entry.excerpt }}</p>

        <div v-if="entry.content" class="prose-entry whitespace-pre-line text-body text-ink">{{ entry.content }}</div>
    </div>
</template>
