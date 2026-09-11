<script setup>
import { reactive, watch } from 'vue';
import Modal from '../Ui/Modal.vue';
import Button from '../Ui/Button.vue';
import Input from '../Ui/Input.vue';
import Checkbox from '../Ui/Checkbox.vue';
import StyledSelect from '../Search/StyledSelect.vue';

/**
 * The settings form for one block, generated from the field list its type
 * declares in blockOptions.js: a select where the field has choices, a
 * checkbox where it is a boolean, a text input otherwise.
 *
 * The block owns the modal rather than the editor doing so, so applying writes
 * through the node view's own updateAttributes and never has to work out which
 * node the caret was in.
 */
const props = defineProps({
    open: { type: Boolean, default: false },
    // { type, label, fields: [{ name, label, type, options?, empty? }] }
    definition: { type: Object, required: true },
    // The block's current attributes, which the form starts from.
    attributes: { type: Object, default: () => ({}) },
});

const emit = defineEmits(['apply', 'update:open']);

const values = reactive({});

/** Refills the form from the block's attributes, freshest each time it opens. */
function resetForm() {
    for (const field of props.definition.fields) {
        const value = props.attributes[field.name];

        values[field.name] = field.type === 'boolean' ? Boolean(value) : (value ?? '');
    }
}

watch(() => props.open, (isOpen) => {
    if (isOpen) {
        resetForm();
    }
}, { immediate: true });

function apply() {
    const result = {};

    for (const field of props.definition.fields) {
        const value = values[field.name];

        // An emptied text field clears the attribute rather than storing "",
        // so the published document carries nothing where nothing was written.
        result[field.name] = field.type === 'boolean'
            ? Boolean(value)
            : (String(value).trim() || null);
    }

    emit('apply', result);
    emit('update:open', false);
}
</script>

<template>
    <Modal
        :open="open"
        :title="`${definition.label} settings`"
        :close-label="`Close ${definition.label.toLowerCase()} settings`"
        @update:open="$emit('update:open', $event)"
    >
        <form class="space-y-4" @submit.prevent="apply">
            <div v-for="field in definition.fields" :key="field.name">
                <label v-if="field.type === 'boolean'" class="flex items-center gap-2 text-body text-neutral-900">
                    <Checkbox v-model="values[field.name]" :aria-label="field.label" />
                    {{ field.label }}
                </label>

                <template v-else>
                    <label :for="`block-${field.name}`" class="mb-1 block text-label uppercase text-neutral-500">{{ field.label }}</label>

                    <StyledSelect
                        v-if="field.type === 'select'"
                        :id="`block-${field.name}`"
                        v-model="values[field.name]"
                        :aria-label="field.label"
                        :options="[{ value: '', label: field.empty ?? 'None' }, ...field.options]"
                    />

                    <Input
                        v-else
                        :id="`block-${field.name}`"
                        v-model="values[field.name]"
                        :aria-label="field.label"
                        :placeholder="field.label"
                    />
                </template>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <Button type="button" variant="secondary" @click="$emit('update:open', false)">Cancel</Button>
                <Button type="submit" variant="primary">Apply</Button>
            </div>
        </form>
    </Modal>
</template>
