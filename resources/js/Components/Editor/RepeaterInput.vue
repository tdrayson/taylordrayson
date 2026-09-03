<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import Input from '../Ui/Input.vue';
import { CONTROL, CONTROL_BORDER } from '../../lib/editor/control.js';

/**
 * A list of rows sharing the same two columns, e.g. a subject's facts
 * (label/value) or its identities (platform/value). Generic over what the
 * columns are called: nothing here is specific to either.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    // list<{value, label, options?}>, the row keys to read/write and their
    // headers. A column carrying its own `options` list (also {value, label})
    // renders as a select rather than a free-text box.
    // Defaults to a plain label/value pair when a field offers no override.
    columns: {
        type: Array,
        default: () => [{ value: 'label', label: 'Label' }, { value: 'value', label: 'Value' }],
    },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const rows = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

function update(index, key, value) {
    const next = rows.value.map((row, position) => (position === index ? { ...row, [key]: value } : row));

    emit('update:modelValue', next);
}

function add() {
    const blank = Object.fromEntries(props.columns.map((column) => [column.value, '']));

    emit('update:modelValue', [...rows.value, blank]);
}

function remove(index) {
    emit('update:modelValue', rows.value.filter((_, position) => position !== index));
}
</script>

<template>
    <div :id="id" class="flex flex-col gap-2">
        <div v-for="(row, index) in rows" :key="index" class="flex items-start gap-2">
            <template v-for="column in columns" :key="column.value">
                <select
                    v-if="column.options"
                    :value="row[column.value] ?? ''"
                    :class="[CONTROL, CONTROL_BORDER, 'flex-1', row[column.value] ? 'text-neutral-900' : 'text-neutral-500']"
                    :aria-label="`${column.label}, row ${index + 1}`"
                    @change="update(index, column.value, $event.target.value)"
                >
                    <option value="" disabled>{{ column.label }}</option>
                    <option v-for="option in column.options" :key="option.value" :value="option.value">
                        {{ option.label }}
                    </option>
                </select>

                <Input
                    v-else
                    :model-value="row[column.value] ?? ''"
                    :placeholder="column.label"
                    class="flex-1"
                    @update:model-value="update(index, column.value, $event)"
                />
            </template>

            <button
                type="button"
                class="flex h-11 shrink-0 items-center justify-center rounded-md px-2 text-neutral-500 transition-colors hover:text-red-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-label="`Remove row ${index + 1}`"
                @click="remove(index)"
            ><Icon name="Delete02Icon" class="size-4" /></button>
        </div>

        <button
            type="button"
            class="flex items-center gap-1.5 self-start rounded-md px-1 py-1 text-meta text-accent-500 transition-colors hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="add"
        >
            <Icon name="PlusSignIcon" class="size-4" /> Add
        </button>
    </div>
</template>
