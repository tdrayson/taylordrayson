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
    <div class="flex items-center justify-between gap-4">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">{{ modelValue ? 'Yes' : 'No' }}</p>
        <Switch v-else :model-value="!!modelValue" @update:model-value="$emit('update:modelValue', $event)" />
    </div>
</template>
