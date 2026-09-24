<script setup>
import { computed } from 'vue';
import DatePicker from '../Overlays/DatePicker.vue';
import Input from '../Ui/Input.vue';
import DurationInput from './DurationInput.vue';
import MultiSelect from './MultiSelect.vue';

const props = defineProps({
    dataType: { type: String, required: true },
    operator: { type: String, required: true },
    options: { type: Array, default: null },
    prefix: { type: String, default: null },
    suffix: { type: String, default: null },
    modelValue: { type: [String, Number, Array], default: null },
});

const emit = defineEmits(['update:modelValue']);

const isWhen = computed(() => ['day', 'month', 'year'].includes(props.dataType));
const isBetween = computed(() => props.operator === 'between' || props.operator === 'not_between');
const isList = computed(() => props.operator === 'is' || props.operator === 'is_not');

const listValue = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue : []),
    set: (value) => emit('update:modelValue', value),
});

const single = computed({
    get: () => (Array.isArray(props.modelValue) ? '' : props.modelValue ?? ''),
    set: (value) => emit('update:modelValue', value),
});

function setPair(index, value) {
    const next = Array.isArray(props.modelValue) ? [...props.modelValue] : ['', ''];
    next[index] = value;
    emit('update:modelValue', next);
}

const firstValue = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue[0] ?? '' : ''),
    set: (value) => setPair(0, value),
});

const secondValue = computed({
    get: () => (Array.isArray(props.modelValue) ? props.modelValue[1] ?? '' : ''),
    set: (value) => setPair(1, value),
});
/** Reads the unit with the value, e.g. "£ Min" or "Max miles". */
function unitLabel(placeholder) {
    return [props.prefix, placeholder, props.suffix].filter(Boolean).join(' ');
}
</script>

<template>
    <template v-if="isWhen">
        <div v-if="isBetween" class="flex items-center gap-2">
            <DatePicker v-model="firstValue" :mode="dataType" placeholder="From" class="flex-1" />
            <DatePicker v-model="secondValue" :mode="dataType" placeholder="To" class="flex-1" />
        </div>
        <DatePicker v-else v-model="single" :mode="dataType" />
    </template>

    <template v-else-if="dataType === 'duration'">
        <div v-if="isBetween" class="flex items-center gap-2">
            <DurationInput v-model="firstValue" class="flex-1" />
            <DurationInput v-model="secondValue" class="flex-1" />
        </div>
        <DurationInput v-else v-model="single" />
    </template>

    <template v-else-if="dataType === 'number' || dataType === 'media'">
        <div v-if="isBetween" class="flex items-center gap-2">
            <Input v-model="firstValue" type="number" :prefix="prefix" :suffix="suffix" placeholder="Min" :aria-label="unitLabel('Min')" class="flex-1" />
            <Input v-model="secondValue" type="number" :prefix="prefix" :suffix="suffix" placeholder="Max" :aria-label="unitLabel('Max')" class="flex-1" />
        </div>
        <Input v-else v-model="single" type="number" :prefix="prefix" :suffix="suffix" placeholder="Value" :aria-label="unitLabel('Value')" />
    </template>

    <template v-else-if="dataType === 'enum'">
        <MultiSelect v-if="isList" v-model="listValue" :options="options ?? []" />
        <Input v-else v-model="single" placeholder="Filter value" aria-label="Filter value" />
    </template>

    <!-- Every non-"none" subject operator (includes/includes_all/excludes)
         takes a list of subjects, so this never falls back to a text input. -->
    <template v-else-if="dataType === 'subject'">
        <MultiSelect v-model="listValue" :options="options ?? []" />
    </template>

    <Input v-else v-model="single" placeholder="Filter value" aria-label="Filter value" />
</template>
