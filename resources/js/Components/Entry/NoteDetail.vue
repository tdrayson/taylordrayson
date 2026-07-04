<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';

const props = defineProps({
    entry: { type: Object, required: true },
});

const tags = computed(() => (Array.isArray(props.entry.tags) ? props.entry.tags : []));
</script>

<template>
    <div class="max-w-reading space-y-4">
        <!-- Notes have no headline, so the content itself is the page's primary text. -->
        <p class="whitespace-pre-line text-lg leading-relaxed">{{ entry.content }}</p>

        <div v-if="tags.length" class="flex flex-wrap gap-2">
            <Pill v-for="tag in tags" :key="tag.slug" :label="tag.name" :href="`/tags/${tag.slug}`" />
        </div>
    </div>
</template>
