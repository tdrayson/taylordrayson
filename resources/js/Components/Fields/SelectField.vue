<script setup>
import Combobox from '../Ui/Combobox.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <Combobox
            :model-value="modelValue ?? ''"
            :options="field.options ?? []"
            :invalid="!!error"
            placeholder="Select"
            @update:model-value="$emit('update:modelValue', $event)"
        />
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
