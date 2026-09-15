<script setup>
import { computed, useSlots } from 'vue';
import { cn } from '../../lib/cn.js';
import { CONTROL } from '../../lib/editor/control.js';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    readonly: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    prefix: { type: String, default: null },
    suffix: { type: String, default: null },
    class: { type: [String, Array, Object], default: '' },
});

defineEmits(['update:modelValue']);

defineOptions({ inheritAttrs: false });

const slots = useSlots();

// A prefix or suffix is text via the prop, or anything (an icon, a button) via the slot of the same name.
const hasPrefix = computed(() => Boolean(props.prefix || slots.prefix));
const hasSuffix = computed(() => Boolean(props.suffix || slots.suffix));
const hasAffix = computed(() => hasPrefix.value || hasSuffix.value);

const border = computed(() => {
    if (props.invalid) {
        return 'border-red-500 focus-within:border-red-500';
    }

    return props.readonly
        ? 'border-neutral-100 focus-within:border-neutral-100'
        : 'border-neutral-100 focus-within:border-accent-500';
});

const wrapperClasses = computed(() =>
    cn(
        CONTROL,
        'flex items-center gap-1.5 py-0',
        border.value,
        props.readonly && 'bg-neutral-50 text-neutral-700 cursor-default',
        props.disabled && 'cursor-not-allowed opacity-50',
        props.class,
    ),
);

const bareClasses = computed(() =>
    cn(
        CONTROL,
        'text-neutral-900 placeholder:text-neutral-500 focus:outline-none',
        props.invalid
            ? 'border-red-500 focus:border-red-500'
            : props.readonly
                ? 'border-neutral-100 focus:border-neutral-100'
                : 'border-neutral-100 focus:border-accent-500',
        props.readonly && 'bg-neutral-50 text-neutral-700 cursor-default',
        props.disabled && 'cursor-not-allowed opacity-50',
        props.class,
    ),
);

const affixInputClasses = computed(() =>
    cn(
        'w-full min-w-0 border-none bg-transparent py-2 placeholder:text-neutral-500 focus:outline-none',
        props.readonly ? 'text-neutral-700 cursor-default' : 'text-neutral-900',
    ),
);
</script>

<template>
    <div v-if="hasAffix" :class="wrapperClasses">
        <span v-if="hasPrefix" class="flex shrink-0 select-none items-center text-neutral-500"><slot name="prefix">{{ prefix }}</slot></span>

        <input
            v-bind="$attrs"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            :readonly="readonly"
            :class="affixInputClasses"
            @input="$emit('update:modelValue', $event.target.value)"
        >

        <span v-if="hasSuffix" class="flex shrink-0 select-none items-center text-neutral-500"><slot name="suffix">{{ suffix }}</slot></span>
    </div>

    <input
        v-else
        v-bind="$attrs"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :readonly="readonly"
        :class="bareClasses"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
