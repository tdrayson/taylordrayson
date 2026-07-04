<script setup>
defineProps({
    entry: { type: Object, required: true },
});
</script>

<template>
    <!-- Notes have no headline, so the content itself is the page's primary text. -->
    <p class="whitespace-pre-line text-lg leading-relaxed">{{ entry.content }}</p>
</template>
