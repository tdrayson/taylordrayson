<script setup>
const props = defineProps({
    modelValue: { type: String, required: true },
    // [{ value, label }] — the selectable options.
    options: { type: Array, required: true },
    // Visible row label (e.g. "Distance").
    label: { type: String, required: true },
    // Group aria-label for the radiogroup (e.g. "Distance unit").
    ariaLabel: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <span class="text-body text-neutral-900">{{ label }}</span>
        <div
            role="radiogroup"
            :aria-label="ariaLabel"
            class="inline-flex rounded-lg border border-neutral-100 p-0.5"
        >
            <button
                v-for="option in options"
                :key="option.value"
                type="button"
                role="radio"
                :aria-checked="modelValue === option.value"
                :aria-label="option.label"
                class="rounded-md px-3 py-1 text-caption font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="modelValue === option.value
                    ? 'bg-neutral-900 text-neutral-0'
                    : 'text-neutral-500 hover:text-neutral-900'"
                @click="emit('update:modelValue', option.value)"
            >
                {{ option.label }}
            </button>
        </div>
    </div>
</template>
