<script setup>
import Icon from '../Ui/Icon.vue';

defineOptions({ inheritAttrs: false });

defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, required: true }, // [{ value, label }]
    placeholder: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="relative">
        <select
            v-bind="$attrs"
            :value="modelValue"
            class="w-full appearance-none rounded-md border border-neutral-100 bg-neutral-0 py-2.5 pl-3 pr-9 text-meta transition-colors focus:border-accent-500 focus:outline-none"
            :class="modelValue === '' ? 'text-neutral-500' : 'text-neutral-900'"
            @change="emit('update:modelValue', $event.target.value)"
        >
            <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
            <option v-for="option in options" :key="option.value" :value="option.value">{{ option.label }}</option>
        </select>
        <Icon name="ArrowDown01Icon" class="pointer-events-none absolute right-3 top-1/2 size-3.5 -translate-y-1/2 text-neutral-500" />
    </div>
</template>
