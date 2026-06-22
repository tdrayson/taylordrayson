<script setup>
import FeedItem from './FeedItem.vue';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <div class="timeline-feed flex flex-col gap-9 pl-14">
        <FeedItem
            v-for="(item, index) in items"
            :key="index"
            v-bind="item"
        />
    </div>
</template>

<style scoped>
.timeline-feed {
    position: relative;
}

.timeline-feed::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 14px;
    bottom: 10px;
    width: 2px;
    background: var(--color-line-2);
    border-radius: 2px;
}
</style>
