<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const trackClasses = computed(() => {
    if (props.readonly) {
        return props.modelValue ? 'cursor-default bg-neutral-300' : 'cursor-default bg-neutral-100';
    }

    return props.modelValue ? 'bg-accent-500' : 'bg-neutral-100';
});

function onClick() {
    if (props.readonly) {
        return;
    }

    emit('update:modelValue', ! props.modelValue);
}
</script>

<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        :aria-readonly="readonly || undefined"
        :disabled="disabled"
        class="relative inline-flex h-6 w-11 shrink-0 items-center rounded-full px-0.5 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2 disabled:opacity-50"
        :class="trackClasses"
        @click="onClick"
    >
        <span
            class="inline-block size-5 rounded-full bg-neutral-0 shadow-sm transition-transform"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>
