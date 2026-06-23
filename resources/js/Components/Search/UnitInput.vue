<script setup>
defineProps({
    modelValue: { type: [String, Number], default: '' },
    prefix: { type: String, default: null },
    suffix: { type: String, default: null },
    placeholder: { type: String, default: 'Value' },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center rounded-md border border-line bg-canvas transition-colors focus-within:border-accent">
        <span v-if="prefix" class="pl-3 text-meta text-ink-3">{{ prefix }}</span>
        <input
            :value="modelValue"
            type="number"
            :placeholder="placeholder"
            class="w-full min-w-0 bg-transparent py-2.5 text-meta text-ink placeholder:text-ink-3 focus:outline-none"
            :class="[prefix ? 'pl-1.5' : 'pl-3', suffix ? 'pr-1.5' : 'pr-3']"
            @input="emit('update:modelValue', $event.target.value)"
        >
        <span v-if="suffix" class="pr-3 text-meta text-ink-3">{{ suffix }}</span>
    </div>
</template>
