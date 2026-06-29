<script setup>
import { computed } from 'vue';
import { Add01Icon, Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Input from '../Ui/Input.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [Object, Array], default: () => ({}) },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

/** Render the object as ordered rows; emit back as an object. */
const rows = computed(() =>
    Object.entries(props.modelValue && typeof props.modelValue === 'object' ? props.modelValue : {})
        .map(([key, value]) => ({ key, value })),
);

function emitRows(next) {
    const object = {};
    for (const row of next) {
        if (row.key !== '') {
            object[row.key] = row.value;
        }
    }
    emit('update:modelValue', object);
}

function update(index, patch) {
    const next = rows.value.map((row, i) => (i === index ? { ...row, ...patch } : row));
    emitRows(next);
}

function add() {
    emitRows([...rows.value, { key: '', value: '' }]);
}

function remove(index) {
    emitRows(rows.value.filter((_, i) => i !== index));
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <div class="flex flex-col gap-2">
            <div v-for="(row, index) in rows" :key="index" class="flex items-center gap-2">
                <Input :model-value="row.key" placeholder="Key" class="flex-1" @update:model-value="update(index, { key: $event })" />
                <Input :model-value="row.value" placeholder="Value" class="flex-1" @update:model-value="update(index, { value: $event })" />
                <button type="button" aria-label="Remove row" class="shrink-0 p-1.5 text-neutral-500 hover:text-red-600" @click="remove(index)">
                    <Icon :icon="Cancel01Icon" class="size-4" />
                </button>
            </div>
            <button
                type="button"
                class="flex items-center gap-1.5 self-start rounded-md px-2 py-1.5 text-label text-neutral-700 hover:bg-neutral-25"
                @click="add"
            >
                <Icon :icon="Add01Icon" class="size-3.5" />
                Add row
            </button>
        </div>
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
