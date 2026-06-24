<script setup>
import { HugeiconsIcon } from '@hugeicons/vue';

defineProps({
    icon: { type: [Array, Object], required: true },
    strokeWidth: { type: [Number, String], default: 1.7 },
});
</script>

<template>
    <HugeiconsIcon :icon="icon" :stroke-width="strokeWidth" color="currentColor" aria-hidden="true" />
</template>
