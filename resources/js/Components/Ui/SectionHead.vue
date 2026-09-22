<script setup>
import Heading from './Heading.vue';

defineProps({
    title: { type: String, required: true },
    meta: { type: String, default: '' },
    metaTitle: { type: String, default: null },
    // 'section' (default) for a sub-section header within an entry; 'title' to
    // match a timeline day/group header, for top-level page sections like the
    // month photo strip that sit alongside DateGroup headings.
    size: { type: String, default: 'section' }, // 'section' | 'title'
});
</script>

<template>
    <div class="mb-4 mt-12 flex items-baseline justify-between gap-4">
        <Heading :size="size">{{ title }}</Heading>
        <span v-if="meta" :title="metaTitle" class="whitespace-nowrap text-xs text-neutral-500">{{ meta }}</span>
    </div>
</template>
