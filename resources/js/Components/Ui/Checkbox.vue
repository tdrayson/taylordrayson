<script setup>
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
        class="flex size-5 shrink-0 items-center justify-center rounded border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-1 disabled:opacity-50"
        :class="modelValue ? 'border-accent-500 bg-accent-500 text-neutral-0' : 'border-neutral-100 bg-neutral-0 text-transparent hover:border-accent-500'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <Icon name="Tick02Icon" class="size-3.5" />
    </button>
</template>
