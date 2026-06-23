<script setup>
import { ref, watch } from 'vue';
import StyledSelect from './StyledSelect.vue';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' }, // canonical: seconds
});

const emit = defineEmits(['update:modelValue']);

const FACTORS = { seconds: 1, minutes: 60, hours: 3600 };
const unitOptions = [
    { value: 'seconds', label: 'seconds' },
    { value: 'minutes', label: 'minutes' },
    { value: 'hours', label: 'hours' },
];

const amount = ref('');
const unit = ref('minutes');

/** The seconds our current amount + unit represent (or '' when empty). */
function toSeconds() {
    return amount.value === '' ? '' : Math.round(Number(amount.value) * FACTORS[unit.value]);
}

function emitSeconds() {
    emit('update:modelValue', toSeconds());
}

const onAmount = (value) => {
    amount.value = value;
    emitSeconds();
};

// Switching unit reinterprets the same amount (20 minutes → 20 hours), like most pickers.
const onUnit = (value) => {
    unit.value = value;
    emitSeconds();
};

// Re-derive a friendly amount + unit only when the value changes externally
// (e.g. a reset), never echoing our own emits.
watch(
    () => props.modelValue,
    (value) => {
        if (`${value}` === `${toSeconds()}`) {
            return;
        }

        if (value === '' || value == null) {
            amount.value = '';

            return;
        }

        const seconds = Number(value);

        if (seconds % 3600 === 0) {
            unit.value = 'hours';
            amount.value = seconds / 3600;
        } else if (seconds % 60 === 0) {
            unit.value = 'minutes';
            amount.value = seconds / 60;
        } else {
            unit.value = 'seconds';
            amount.value = seconds;
        }
    },
    { immediate: true }
);
</script>

<template>
    <div class="flex items-center gap-2">
        <input
            :value="amount"
            type="number"
            min="0"
            placeholder="0"
            class="w-20 shrink-0 rounded-md border border-line bg-canvas px-3 py-2.5 text-meta text-ink transition-colors placeholder:text-ink-3 focus:border-accent focus:outline-none"
            @input="onAmount($event.target.value)"
        >
        <div class="flex-1">
            <StyledSelect :model-value="unit" :options="unitOptions" @update:model-value="onUnit" />
        </div>
    </div>
</template>
