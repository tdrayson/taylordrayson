<script setup>
import { computed } from 'vue';
import TextField from './TextField.vue';
import NumberField from './NumberField.vue';
import TextareaField from './TextareaField.vue';
import DateField from './DateField.vue';
import DateTimeField from './DateTimeField.vue';
import BooleanField from './BooleanField.vue';
import SelectField from './SelectField.vue';
import JsonField from './JsonField.vue';
import EditorField from './EditorField.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { default: null },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const COMPONENTS = {
    text: TextField,
    number: NumberField,
    textarea: TextareaField,
    date: DateField,
    datetime: DateTimeField,
    boolean: BooleanField,
    select: SelectField,
    json: JsonField,
    editor: EditorField,
};

const component = computed(() => COMPONENTS[props.field.type] ?? TextField);
</script>

<template>
    <component
        :is="component"
        :field="field"
        :mode="mode"
        :error="error"
        :model-value="modelValue"
        @update:model-value="$emit('update:modelValue', $event)"
    />
</template>
