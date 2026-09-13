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
    // 'md' is the current boxed control. 'sm' is a compact toolbar variant; ignored by 'bare'.
    size: { type: String, default: 'md' },
    class: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

// True for the inline, chrome-free variant used inside a line of text.
const isBare = computed(() => props.variant === 'bare');

// Nothing chosen yet reads as the placeholder row, greyed like a placeholder. An
// empty value with no placeholder is a real option, so it stays dark.
const showsPlaceholder = computed(() => Boolean(props.placeholder)
    && (props.modelValue === null || props.modelValue === undefined || props.modelValue === ''));

// Boxed border colour swaps to red when the field failed validation.
const boxedBorder = computed(() => (props.invalid
    ? 'border-red-500 focus:border-red-500'
    : 'border-neutral-100 focus:border-accent-500'));

// Boxed sizing: 'sm' sizes to its content for toolbars and keeps a focus ring,
// red when invalid, since a border-colour change alone is too subtle at that size.
const boxedSize = computed(() => (props.size === 'sm'
    ? cn('w-auto rounded-md border bg-neutral-0 py-1 pl-2 pr-7 text-meta transition-colors focus-visible:ring-2', props.invalid ? 'focus-visible:ring-red-500' : 'focus-visible:ring-accent-500')
    : cn(CONTROL, 'pr-9')));

// Final class list for the <select>, merged so a caller's class can override ours.
const selectClasses = computed(() => cn(
    isBare.value
        ? 'cursor-pointer appearance-none bg-transparent pr-5 text-meta font-medium transition-colors hover:text-accent-500 focus:outline-none focus-visible:underline focus-visible:underline-offset-4'
        : cn('appearance-none focus:outline-none', boxedSize.value, boxedBorder.value),
    showsPlaceholder.value ? 'text-neutral-500' : 'text-neutral-900',
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
            <option v-if="placeholder" value="" :disabled="! clearable">{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" :class="iconClass" />
    </div>
</template>
