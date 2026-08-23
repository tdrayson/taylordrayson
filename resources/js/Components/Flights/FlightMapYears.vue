<script setup>
import { computed } from 'vue';
import Pill from '../Ui/Pill.vue';

/**
 * Year filter for the flight globe map: a button group of Pills, since
 * Pill.vue itself is never interactive.
 * @param {Array<number>} years Years present in the data, most recent first.
 * @param {number|null} modelValue Selected year, or null for all time.
 */
const props = defineProps({
    years: { type: Array, default: () => [] },
    modelValue: { type: Number, default: null },
});

const emit = defineEmits(['update:modelValue']);

// "All time" always leads so there is a way back to the unfiltered view.
const options = computed(() => [
    { label: 'All time', value: null },
    ...props.years.map((year) => ({ label: String(year), value: year })),
]);
</script>

<template>
    <div role="group" aria-label="Filter flights by year" class="flex flex-wrap gap-2">
        <button
            v-for="option in options"
            :key="option.value ?? 'all'"
            type="button"
            class="rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :aria-pressed="modelValue === option.value"
            @click="emit('update:modelValue', option.value)"
        >
            <Pill :label="option.label" :variant="modelValue === option.value ? 'accent' : 'default'" />
        </button>
    </div>
</template>
