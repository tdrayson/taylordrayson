<script setup>
import { Tick02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="checkbox"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="flex size-5 shrink-0 items-center justify-center rounded border transition-colors focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'border-accent bg-accent text-canvas' : 'border-line bg-canvas text-transparent hover:border-accent'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <Icon :icon="Tick02Icon" class="size-3.5" />
    </button>
</template>
