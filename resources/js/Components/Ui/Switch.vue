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
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full px-0.5 transition-colors focus-visible:outline-none disabled:cursor-not-allowed disabled:opacity-50"
        :class="modelValue ? 'bg-accent' : 'bg-line'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="inline-block size-5 rounded-full bg-canvas shadow-sm transition-transform"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>
