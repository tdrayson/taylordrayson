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

// A unique radio-group name per instance so native radios group correctly
// (which gives free arrow-key roving between options).
const groupName = `setting-${props.ariaLabel.toLowerCase().replace(/\s+/g, '-')}`;
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <span class="text-body text-neutral-900">{{ label }}</span>
        <div
            role="radiogroup"
            :aria-label="ariaLabel"
            class="inline-flex rounded-lg border border-neutral-100 p-0.5"
        >
            <label
                v-for="option in options"
                :key="option.value"
                :aria-label="option.label"
                class="cursor-pointer"
            >
                <!-- Real radio input, visually hidden; the styled span below is
                     its `peer`, so it reflects checked/hover/focus state. -->
                <input
                    type="radio"
                    class="peer sr-only"
                    :name="groupName"
                    :value="option.value"
                    :checked="modelValue === option.value"
                    @change="emit('update:modelValue', option.value)"
                >
                <span
                    class="block rounded-md px-3 py-1 text-caption font-semibold text-neutral-500 transition-colors duration-150 peer-hover:text-neutral-900 peer-checked:bg-accent-500 peer-checked:text-neutral-0 peer-focus-visible:ring-2 peer-focus-visible:ring-accent-500"
                >{{ option.label }}</span>
            </label>
        </div>
    </div>
</template>
