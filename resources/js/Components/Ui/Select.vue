<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import Icon from './Icon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true }, // [{ value, label }]
    placeholder: { type: String, default: null },
    // 'boxed' is the form control. 'bare' drops the border, background and
    // width so the select sits in a line of text as just a word and a chevron.
    variant: { type: String, default: 'boxed' },
    invalid: { type: Boolean, default: false },
    // 'md' is the current boxed control. 'sm' is a compact toolbar variant; ignored by 'bare'.
    size: { type: String, default: 'md' },
    class: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

// True for the inline, chrome-free variant used inside a line of text.
const isBare = computed(() => props.variant === 'bare');

// Boxed border colour swaps to red when the field failed validation.
const boxedBorder = computed(() => (props.invalid
    ? 'border-red-500 focus:border-red-500'
    : 'border-neutral-100 focus:border-accent-500'));

// Boxed sizing: 'sm' is compact for toolbars and keeps a focus ring since its
// border-colour change alone is too subtle at that size.
const boxedSize = computed(() => (props.size === 'sm'
    ? 'rounded-md border py-1 pl-2 pr-7 focus-visible:ring-2 focus-visible:ring-accent-500'
    : 'w-full rounded-md border py-2.5 pl-3 pr-9'));

// Final class list for the <select>, merged so a caller's class (e.g. min-h-11) can override ours.
const selectClasses = computed(() => cn(
    isBare.value
        ? 'appearance-none bg-transparent pr-5 text-meta font-medium focus:outline-none focus-visible:underline focus-visible:underline-offset-4'
        : cn('w-full appearance-none bg-neutral-0 text-meta transition-colors focus:outline-none', boxedBorder.value, boxedSize.value),
    props.modelValue === '' ? 'text-neutral-500' : 'text-neutral-900',
    isBare.value && 'cursor-pointer transition-colors hover:text-accent-500',
    props.class,
));

// Chevron position/size tracks the variant and size so it stays centred against the select's own padding.
const iconClass = computed(() => {
    if (isBare.value) {
        return 'pointer-events-none absolute right-0 top-1/2 size-3 -translate-y-1/2 text-neutral-500';
    }

    return props.size === 'sm'
        ? 'pointer-events-none absolute right-2 top-1/2 size-3 -translate-y-1/2 text-neutral-500'
        : 'pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-neutral-500';
});
</script>

<template>
    <div :class="isBare ? 'relative inline-flex' : 'relative'">
        <select
            v-bind="$attrs"
            :value="modelValue"
            :class="selectClasses"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" :class="iconClass" />
    </div>
</template>
