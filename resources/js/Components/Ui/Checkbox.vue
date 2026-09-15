<script setup>
import { computed } from 'vue';
import Icon from './Icon.vue';

const props = defineProps({
    modelValue: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const stateClasses = computed(() => {
    if (props.readonly) {
        return props.modelValue
            ? 'cursor-default border-neutral-100 bg-neutral-50 text-neutral-500'
            : 'cursor-default border-neutral-100 bg-neutral-50 text-transparent';
    }

    return props.modelValue
        ? 'border-accent-500 bg-accent-500 text-neutral-0'
        : 'border-neutral-100 bg-neutral-0 text-transparent hover:border-accent-500';
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
        role="checkbox"
        :aria-checked="modelValue"
        :aria-readonly="readonly || undefined"
        :disabled="disabled"
        class="flex size-5 shrink-0 items-center justify-center rounded border transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-1 disabled:opacity-50"
        :class="stateClasses"
        @click="onClick"
    >
        <Icon name="Tick02Icon" class="size-3.5" />
    </button>
</template>
