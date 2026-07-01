<script setup>
const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full px-0.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-accent-500' : 'bg-neutral-100'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block size-5 rounded-full bg-neutral-0 shadow-sm transition-transform"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>
