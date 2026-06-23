<script setup>
import Icon from './Icon.vue';

defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    icon: { type: [Array, Object], default: null },
    circle: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="inline-badge inline-block size-7 overflow-hidden bg-surface"
        :class="circle ? 'rounded-full' : 'rounded-sm'"
        aria-hidden="true"
    >
        <img
            v-if="src"
            :src="src"
            :alt="alt"
            class="block size-full object-contain"
        >
        <Icon v-else-if="icon" :icon="icon" class="size-full" />
    </span>
</template>

<style scoped>
/* Seat the badge just below the text baseline (à la inline avatars/FA icons). */
.inline-badge {
    vertical-align: -0.25em;
}
</style>
