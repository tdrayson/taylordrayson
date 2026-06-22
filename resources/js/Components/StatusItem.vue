<script setup>
import Icon from './Icon.vue';

defineProps({
    icon: { type: [Array, Object], required: true },
});
</script>

<template>
    <span class="inline-flex items-center gap-2 whitespace-nowrap">
        <Icon :icon="icon" class="size-4 text-ink-3" />
        <slot />
    </span>
</template>
