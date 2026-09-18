<script setup>
import Icon from './Icon.vue';

defineProps({
    src: { type: String, default: null },
    alt: { type: String, default: '' },
    icon: { type: [Array, Object, String], default: null },
    circle: { type: Boolean, default: false },
});
</script>

<template>
    <span
        class="inline-block size-7 overflow-hidden bg-neutral-25 align-drop-25"
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
