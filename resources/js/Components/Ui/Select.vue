<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import { CONTROL } from '../../lib/editor/control.js';
import Icon from './Icon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true }, // [{ value, label }]
    placeholder: { type: String, default: null },
    // 'boxed' is the form control, shaped like every other one. 'bare' drops
    // the border, background and width so the select sits in a line of text as
    // just a word and a chevron.
    variant: { type: String, default: 'boxed' },
    // The server refused this field, so it is drawn the way a bad input is.
    invalid: { type: Boolean, default: false },
    // Whether the placeholder row can be chosen again, which is how an
    // optional field is emptied after something has been picked.
    clearable: { type: Boolean, default: false },
    class: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

const isBare = computed(() => props.variant === 'bare');

// Nothing chosen yet reads as the placeholder row, greyed like a placeholder.
const isEmpty = computed(() => props.modelValue === null || props.modelValue === undefined || props.modelValue === '');

const border = computed(() => (props.invalid
    ? 'border-red-500 focus:border-red-500'
    : 'border-neutral-100 focus:border-accent-500'));

const selectClass = computed(() => (isBare.value
    ? cn(
        'cursor-pointer appearance-none bg-transparent pr-5 text-meta font-medium transition-colors hover:text-accent-500 focus:outline-none focus-visible:underline focus-visible:underline-offset-4',
        props.class,
    )
    : cn(CONTROL, 'appearance-none pr-9', border.value, 'focus:outline-none', props.class)));

const iconClass = computed(() => (isBare.value
    ? 'pointer-events-none absolute right-0 top-1/2 size-3 -translate-y-1/2 text-neutral-500'
    : 'pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-neutral-500'));
</script>

<template>
    <div :class="isBare ? 'relative inline-flex' : 'relative'">
        <select
            v-bind="$attrs"
            :value="modelValue"
            :class="[selectClass, isEmpty ? 'text-neutral-500' : 'text-neutral-900']"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" :disabled="! clearable">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" :class="iconClass" />
    </div>
</template>
