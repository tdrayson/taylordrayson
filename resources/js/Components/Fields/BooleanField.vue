<script setup>
import Switch from '../Ui/Switch.vue';

defineProps({
    field: { type: Object, required: true },
    modelValue: { type: Boolean, default: false },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);
</script>

<template>
    <div class="flex items-center justify-between gap-3">
        <label class="text-caption font-medium text-neutral-600">{{ field.label }}</label>
        <Switch :model-value="!!modelValue" @update:model-value="$emit('update:modelValue', $event)" />
    </div>
</template>
