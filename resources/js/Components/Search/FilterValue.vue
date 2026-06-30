<script setup>
import { computed } from 'vue';
import DatePicker from '../Overlays/DatePicker.vue';
import UnitInput from './UnitInput.vue';
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

const inputClass =
    'w-full rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2.5 text-meta text-neutral-900 transition-colors placeholder:text-neutral-500 focus:border-accent-500 focus:outline-none';

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
</script>

<template>
    <!-- When (day / month / year), single or a from–to range -->
    <template v-if="isWhen">
        <div v-if="isBetween" class="flex items-center gap-2">
            <DatePicker v-model="firstValue" :mode="dataType" placeholder="From" class="flex-1" />
            <DatePicker v-model="secondValue" :mode="dataType" placeholder="To" class="flex-1" />
        </div>
        <DatePicker v-else v-model="single" :mode="dataType" />
    </template>

    <!-- Duration (amount + unit, stored as seconds) -->
    <template v-else-if="dataType === 'duration'">
        <div v-if="isBetween" class="flex items-center gap-2">
            <DurationInput v-model="firstValue" class="flex-1" />
            <DurationInput v-model="secondValue" class="flex-1" />
        </div>
        <DurationInput v-else v-model="single" />
    </template>

    <!-- Number / photo count (with optional unit prefix/suffix) -->
    <template v-else-if="dataType === 'number' || dataType === 'media'">
        <div v-if="isBetween" class="flex items-center gap-2">
            <UnitInput v-model="firstValue" :prefix="prefix" :suffix="suffix" placeholder="Min" class="flex-1" />
            <UnitInput v-model="secondValue" :prefix="prefix" :suffix="suffix" placeholder="Max" class="flex-1" />
        </div>
        <UnitInput v-else v-model="single" :prefix="prefix" :suffix="suffix" />
    </template>

    <!-- Enum: is / is not → searchable multi-select; equals / contains → text -->
    <template v-else-if="dataType === 'enum'">
        <MultiSelect v-if="isList" v-model="listValue" :options="options ?? []" />
        <input v-else v-model="single" type="text" placeholder="Filter value" aria-label="Filter value" :class="inputClass">
    </template>

    <!-- Text -->
    <input v-else v-model="single" type="text" placeholder="Filter value" aria-label="Filter value" :class="inputClass">
</template>
