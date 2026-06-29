<script setup>
import { computed } from 'vue';
import Combobox from '../Ui/Combobox.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: String, default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

// Older engines lack Intl.supportedValuesOf; keep a small useful fallback.
const FALLBACK = ['UTC', 'Europe/London', 'Europe/Warsaw', 'Europe/Paris', 'America/New_York', 'America/Los_Angeles', 'Asia/Tokyo'];

const options = computed(() => {
    const zones = typeof Intl.supportedValuesOf === 'function' ? Intl.supportedValuesOf('timeZone') : FALLBACK;

    return zones.map((zone) => ({ value: zone, label: zone.replace(/_/g, ' ') }));
});
</script>

<template>
    <div class="flex flex-col gap-2">
        <label class="text-caption font-medium text-neutral-600">{{ field.label }}</label>
        <Combobox
            :model-value="modelValue ?? ''"
            :options="options"
            :invalid="!!error"
            placeholder="Select timezone"
            @update:model-value="$emit('update:modelValue', $event)"
        />
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
