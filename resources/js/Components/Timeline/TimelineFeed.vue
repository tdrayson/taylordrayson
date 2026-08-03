<script setup>
import FeedItem from './FeedItem.vue';
import FeedRail from './FeedRail.vue';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <FeedRail>
        <FeedItem
            v-for="(item, index) in items"
            :key="index"
            v-bind="item"
        />
    </FeedRail>
</template>
