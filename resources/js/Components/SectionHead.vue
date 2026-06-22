<script setup>
defineProps({
    title: { type: String, required: true },
    meta: { type: String, default: '' },
});
</script>

<template>
    <div class="mb-4 mt-12 flex items-baseline justify-between gap-4">
        <h2 class="font-display text-section">{{ title }}</h2>
        <span v-if="meta" class="whitespace-nowrap text-xs text-ink-3">{{ meta }}</span>
    </div>
</template>
