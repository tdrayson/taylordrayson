<script setup>
import { LEVELS } from './shared.js';

defineProps({
    size: { type: String, default: 'size-5' },
});
</script>

<template>
    <span class="flex gap-2" aria-hidden="true">
        <span v-for="(fill, level) in LEVELS" :key="level" :class="[size, fill]" class="rounded" />
    </span>
</template>
