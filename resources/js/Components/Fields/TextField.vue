<script setup>
import Input from '../Ui/Input.vue';

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
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="modelValue !== '' && modelValue !== null">{{ modelValue }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <Input
                :model-value="modelValue ?? ''"
                :invalid="!!error"
                @update:model-value="$emit('update:modelValue', $event)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
