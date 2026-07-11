<script setup>
import { computed } from 'vue';

const props = defineProps({
    title: { type: String, required: true },
    meta: { type: String, default: '' },
    // 'section' (default) for a sub-section header within an entry; 'title' to
    // match a timeline day/group header, for top-level page sections like the
    // month photo strip that sit alongside DateGroup headings.
    size: { type: String, default: 'section' }, // 'section' | 'title'
});

const titleClass = computed(() => (props.size === 'title' ? 'text-item-title' : 'text-section'));
</script>

<template>
    <div class="mb-4 mt-12 flex items-baseline justify-between gap-4">
        <h2 class="font-display" :class="titleClass">{{ title }}</h2>
        <span v-if="meta" class="whitespace-nowrap text-xs text-neutral-500">{{ meta }}</span>
    </div>
</template>
