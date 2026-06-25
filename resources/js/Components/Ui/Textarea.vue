<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: '' },
    rows: { type: [String, Number], default: 4 },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    class: { type: [String, Array, Object], default: '' },
});

defineEmits(['update:modelValue']);

const classes = computed(() =>
    cn(
        'w-full resize-y rounded-md border bg-neutral-0 px-3 py-2 text-meta text-neutral-900 transition-colors placeholder:text-neutral-500 focus:outline-none',
        props.invalid ? 'border-red-500 focus:border-red-500' : 'border-neutral-100 focus:border-accent-500',
        props.disabled && 'cursor-not-allowed opacity-50',
        props.class,
    ),
);
</script>

<template>
    <textarea
        :value="modelValue"
        :placeholder="placeholder"
        :rows="rows"
        :disabled="disabled"
        :class="classes"
        @input="$emit('update:modelValue', $event.target.value)"
    ></textarea>
</template>
