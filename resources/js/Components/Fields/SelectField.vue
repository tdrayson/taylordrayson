<script setup>
import { computed } from 'vue';
import StyledSelect from '../Search/StyledSelect.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [String, Number], default: '' },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const displayLabel = computed(() => {
    const match = (props.field.options ?? []).find((option) => option.value === props.modelValue);

    return match ? match.label : null;
});
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <p v-if="mode === 'display'" class="text-meta text-neutral-900">
            <span v-if="displayLabel">{{ displayLabel }}</span>
            <span v-else class="text-neutral-500">Not set</span>
        </p>
        <template v-else>
            <StyledSelect
                :model-value="modelValue ?? ''"
                :options="field.options ?? []"
                placeholder="Select"
                @update:model-value="$emit('update:modelValue', $event)"
            />
            <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
        </template>
    </div>
</template>
