<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    type: { type: String, default: 'text' },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    prefix: { type: String, default: null },
    suffix: { type: String, default: null },
    class: { type: [String, Array, Object], default: '' },
});

defineEmits(['update:modelValue']);

defineOptions({ inheritAttrs: false });

const hasAffix = computed(() => Boolean(props.prefix || props.suffix));

const border = computed(() =>
    props.invalid
        ? 'border-red-500 focus-within:border-red-500'
        : 'border-neutral-100 focus-within:border-accent-500',
);

const wrapperClasses = computed(() =>
    cn(
        'flex w-full items-center gap-1.5 rounded-md border bg-neutral-0 px-3 text-meta transition-colors',
        border.value,
        props.disabled && 'cursor-not-allowed opacity-50',
        props.class,
    ),
);

const bareClasses = computed(() =>
    cn(
        'w-full rounded-md border bg-neutral-0 px-3 py-2.5 text-meta text-neutral-900 transition-colors placeholder:text-neutral-500 focus:outline-none',
        props.invalid ? 'border-red-500 focus:border-red-500' : 'border-neutral-100 focus:border-accent-500',
        props.disabled && 'cursor-not-allowed opacity-50',
        props.class,
    ),
);
</script>

<template>
    <div v-if="hasAffix" :class="wrapperClasses">
        <span v-if="prefix" class="shrink-0 select-none text-neutral-500">{{ prefix }}</span>

        <input
            v-bind="$attrs"
            :type="type"
            :value="modelValue"
            :placeholder="placeholder"
            :disabled="disabled"
            class="w-full min-w-0 border-none bg-transparent py-2.5 text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
            @input="$emit('update:modelValue', $event.target.value)"
        >

        <span v-if="suffix" class="shrink-0 select-none text-neutral-500">{{ suffix }}</span>
    </div>

    <input
        v-else
        v-bind="$attrs"
        :type="type"
        :value="modelValue"
        :placeholder="placeholder"
        :disabled="disabled"
        :class="bareClasses"
        @input="$emit('update:modelValue', $event.target.value)"
    >
</template>
