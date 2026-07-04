<script setup>
defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <p class="whitespace-pre-line text-base leading-relaxed">{{ entry.content }}</p>
</template>
