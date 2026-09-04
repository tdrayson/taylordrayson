<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true }, // [{ value, label }]
    placeholder: { type: String, default: null },
    // 'boxed' is the form control. 'bare' drops the border, background and
    // width so the select sits in a line of text as just a word and a chevron.
    variant: { type: String, default: 'boxed' },
});

const emit = defineEmits(['update:modelValue']);

const isBare = computed(() => props.variant === 'bare');

const selectClass = computed(() => (isBare.value
    ? 'appearance-none bg-transparent pr-5 text-meta font-medium focus:outline-none focus-visible:underline focus-visible:underline-offset-4'
    : 'w-full appearance-none rounded-md border border-neutral-100 bg-neutral-0 py-2.5 pl-3 pr-9 text-meta transition-colors focus:border-accent-500 focus:outline-none'));

const iconClass = computed(() => (isBare.value
    ? 'pointer-events-none absolute right-0 top-1/2 size-3 -translate-y-1/2 text-neutral-500'
    : 'pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-neutral-500'));
</script>

<template>
    <div :class="isBare ? 'relative inline-flex' : 'relative'">
        <select
            v-bind="$attrs"
            :value="modelValue"
            :class="[selectClass, modelValue === '' ? 'text-neutral-500' : 'text-neutral-900', isBare ? 'cursor-pointer transition-colors hover:text-accent-500' : '']"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" :class="iconClass" />
    </div>
</template>
