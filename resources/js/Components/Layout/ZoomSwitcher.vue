<script setup>
// An option may carry `hasError` to flag something inside it needing attention.
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
    <div class="inline-flex gap-0.5 rounded-md bg-neutral-25 p-1">
        <button
            v-for="option in options"
            :key="option.value"
            type="button"
            :aria-pressed="modelValue === option.value"
            class="rounded-sm px-4 py-1.5 text-sm font-semibold transition-colors focus-visible:outline-offset-0"
            :class="modelValue === option.value ? 'bg-neutral-0 text-neutral-900 shadow-sm' : 'text-neutral-500 hover:text-neutral-900'"
            @click="$emit('update:modelValue', option.value)"
        >
            {{ option.label }}
            <template v-if="option.hasError">
                <span class="ml-1 inline-block size-1.5 rounded-full bg-red-600 align-middle" aria-hidden="true" />
                <span class="sr-only">, needs fixing</span>
            </template>
        </button>
    </div>
</template>
