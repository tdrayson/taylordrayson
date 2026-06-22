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
    <span class="mx-1 inline-flex size-8 items-center justify-center p-1 align-middle">
        <img
            v-if="src"
            :src="src"
            :alt="alt"
            class="size-full object-contain"
            :class="circle ? 'rounded-full' : 'rounded-xs'"
        >
        <Icon v-else-if="icon" :icon="icon" class="size-4" />
    </span>
</template>
