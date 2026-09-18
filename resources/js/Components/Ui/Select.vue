<script setup>
import { computed } from 'vue';
import { cn } from '../../lib/cn.js';
import { READONLY } from '../../lib/editor/control.js';
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
    readonly: { type: Boolean, default: false },
    // 'md' is the current boxed control. 'sm' is a compact toolbar variant; ignored by 'bare'.
    size: { type: String, default: 'md' },
    class: { type: [String, Array, Object], default: '' },
});

const emit = defineEmits(['update:modelValue']);

// True for the inline, chrome-free variant used inside a line of text.
const isBare = computed(() => props.variant === 'bare');

// Boxed border colour swaps to red when the field failed validation, or stays neutral when read-only.
const boxedBorder = computed(() => {
    if (props.invalid) {
        return 'border-red-500 focus:border-red-500';
    }

    return props.readonly ? 'border-neutral-100 focus:border-neutral-100' : 'border-neutral-100 focus:border-accent-500';
});

// Boxed sizing: 'sm' sizes to its content for toolbars and keeps a focus ring,
// red when invalid, since a border-colour change alone is too subtle at that size.
const boxedSize = computed(() => (props.size === 'sm'
    ? cn('w-auto rounded-md border py-1 pl-2 pr-7 focus-visible:ring-2', props.invalid ? 'focus-visible:ring-red-500' : 'focus-visible:ring-accent-500')
    : 'w-full min-h-11 rounded-md border py-2.5 pl-3 pr-9'));

// Final class list for the <select>, merged so a caller's class can override ours.
const selectClasses = computed(() => cn(
    isBare.value
        ? 'appearance-none bg-transparent pr-5 text-sm font-medium focus:outline-none focus-visible:underline focus-visible:underline-offset-4'
        : cn('w-full appearance-none bg-neutral-0 text-sm transition-colors focus:outline-none', boxedBorder.value, boxedSize.value),
    props.placeholder && props.modelValue === '' ? 'text-neutral-500' : 'text-neutral-900',
    isBare.value && ! props.readonly && 'cursor-pointer transition-colors hover:text-accent-500',
    props.readonly && (isBare.value ? 'cursor-default' : READONLY),
    props.class,
));

// Keys that open the native picker or step through options; Tab is left alone
// so a read-only select stays reachable by keyboard.
const BLOCKED_KEYS = [' ', 'Enter', 'ArrowUp', 'ArrowDown', 'ArrowLeft', 'ArrowRight'];

/** A native select has no readonly attribute, so opening it is blocked by hand. */
function guardInteraction(event) {
    if (props.readonly && (event.type === 'mousedown' || BLOCKED_KEYS.includes(event.key))) {
        event.preventDefault();
    }
}

function onChange(event) {
    if (props.readonly) {
        return;
    }

    emit('update:modelValue', event.target.value);
}

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
            :aria-readonly="readonly || undefined"
            :class="selectClasses"
            @mousedown="guardInteraction"
            @keydown="guardInteraction"
            @change="onChange"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" :class="iconClass" />
    </div>
</template>
