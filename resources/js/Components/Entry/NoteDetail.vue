<script setup>
defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <p class="whitespace-pre-line text-body text-lg text-ink-2">{{ entry.content }}</p>
</template>
