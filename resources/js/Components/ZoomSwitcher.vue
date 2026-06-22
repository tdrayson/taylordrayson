<script setup>
defineProps({
    modelValue: { type: String, default: 'day' },
    options: {
        type: Array,
        default: () => [
            { value: 'day', label: 'Day' },
            { value: 'month', label: 'Month' },
            { value: 'year', label: 'Year' },
        ],
    },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="inline-flex gap-0.5 rounded-md bg-surface p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="modelValue === option.value"
            class="rounded-sm px-4 py-1.5 text-sm font-semibold transition-colors"
            :class="modelValue === option.value ? 'bg-canvas text-ink shadow-sm' : 'text-ink-3 hover:text-ink'"
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
        </button>
    </div>
</template>
