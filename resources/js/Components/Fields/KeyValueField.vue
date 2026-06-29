<script setup>
import { ref } from 'vue';
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

/** Local rows are the source of truth so a blank in-flight row survives editing. */
function toRows(value) {
    return Object.entries(value && typeof value === 'object' ? value : {}).map(([key, val]) => ({ key, value: val }));
}

const rows = ref(toRows(props.modelValue));

function emitObject() {
    const object = {};
    for (const row of rows.value) {
        if (row.key !== '') {
            object[row.key] = row.value;
        }
    }
    emit('update:modelValue', object);
}

function update(index, patch) {
    rows.value = rows.value.map((row, i) => (i === index ? { ...row, ...patch } : row));
    emitObject();
}

function add() {
    rows.value = [...rows.value, { key: '', value: '' }];
}

function remove(index) {
    rows.value = rows.value.filter((_, i) => i !== index);
    emitObject();
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
