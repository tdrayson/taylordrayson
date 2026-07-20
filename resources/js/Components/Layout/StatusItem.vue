<script setup>
import Icon from '../Ui/Icon.vue';

defineProps({
    icon: { type: [Array, Object, String], required: true },
});
</script>

<template>
    <span class="inline-flex items-center gap-2 whitespace-nowrap">
        <Icon :icon="icon" class="size-4 text-neutral-500" />
        <slot />
    </span>
</template>
