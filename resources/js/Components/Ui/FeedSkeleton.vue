<script setup>
import Skeleton from './Skeleton.vue';

defineProps({
    // Date groups to draw. Three fills a viewport without overstating how much
    // is coming.
    count: { type: Number, default: 3 },
});
</script>

<template>
    <div class="space-y-6">
        <div v-for="i in count" :key="i" class="space-y-3">
            <Skeleton class="h-6 w-48" />
            <Skeleton variant="card" class="h-24" />
        </div>
    </div>
</template>
