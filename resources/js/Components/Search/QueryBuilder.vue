<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Delete02Icon, PlusSignIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import Button from '../Ui/Button.vue';
import FilterValue from './FilterValue.vue';
import FieldPicker from './FieldPicker.vue';
import StyledSelect from './StyledSelect.vue';

const props = defineProps({
    schema: { type: Array, required: true },
    filter: { type: Array, default: () => [] },
});

const OPERATOR_LABELS = {
    contains: 'contains', not_contains: 'does not contain', equals: 'equals', starts_with: 'starts with', ends_with: 'ends with',
    is: 'equals', is_not: 'not equal',
    eq: 'equals', neq: 'does not equal', gt: 'greater than', gte: 'greater than or equal', lt: 'less than', lte: 'less than or equal',
    between: 'between', not_between: 'not between',
    has_any: 'has any', has_none: 'has none',
    on: 'on', not_on: 'not on', in: 'is', not_in: 'is not', before: 'before', after: 'after',
};

const schemaByType = computed(() => Object.fromEntries(props.schema.map((type) => [type.type, type])));
const typeOptions = computed(() => props.schema.map((type) => ({ value: type.type, label: type.label })));

const fieldsOf = (type) => schemaByType.value[type]?.fields ?? [];
const fieldDef = (type, key) => fieldsOf(type).find((field) => field.key === key) ?? null;
const operatorOptions = (type, key) =>
    (fieldDef(type, key)?.operators ?? []).map((operator) => ({ value: operator, label: OPERATOR_LABELS[operator] ?? operator }));

// The value shape an operator expects: none, a [from, to] pair, a multi-select
// list, or a scalar.
function valueShape(operator) {
    if (operator === 'has_any' || operator === 'has_none') {
        return 'none';
    }

    if (operator === 'between' || operator === 'not_between') {
        return 'pair';
    }

    if (operator === 'is' || operator === 'is_not') {
        return 'list';
    }

    return 'single';
}

const blankValue = (operator) => {
    const shape = valueShape(operator);

    return shape === 'pair' ? ['', ''] : shape === 'list' ? [] : shape === 'none' ? null : '';
};

function freshCondition(type) {
    const field = fieldsOf(type)[0];

    return { field: field.key, operator: field.operators[0], value: blankValue(field.operators[0]) };
}

// Groups start with no type so the first select shows a placeholder; conditions
// appear once a type is chosen.
function freshGroup() {
    return { type: '', conditions: [] };
}

const groups = ref(
    props.filter.length ? JSON.parse(JSON.stringify(props.filter)) : [freshGroup()]
);

function changeType(group, type) {
    group.type = type;
    group.conditions = [freshCondition(type)];
}

function changeField(condition, type, key) {
    const def = fieldDef(type, key);
    condition.field = key;
    condition.operator = def.operators[0];
    condition.value = blankValue(def.operators[0]);
}

// Keep the value across operator changes within a field, reshaping it only when the
// new operator expects a different shape (single ↔ pair ↔ multi-select list).
function changeOperator(condition, operator) {
    const from = valueShape(condition.operator);
    const to = valueShape(operator);
    const value = condition.value;

    condition.operator = operator;

    if (from === to) {
        return;
    }

    if (to === 'pair') {
        condition.value = [from === 'single' ? value ?? '' : Array.isArray(value) ? value[0] ?? '' : '', ''];
    } else if (to === 'list') {
        condition.value = from === 'single' && value ? [value] : [];
    } else {
        condition.value = Array.isArray(value) ? value[0] ?? '' : '';
    }
}

const addCondition = (group) => group.conditions.push(freshCondition(group.type));
const addGroupAt = (index) => groups.value.splice(index, 0, freshGroup());

// Deleting a group's last condition removes the whole group (keeping at least one).
function removeCondition(group, index) {
    group.conditions.splice(index, 1);

    if (group.conditions.length === 0) {
        groups.value.splice(groups.value.indexOf(group), 1);

        if (groups.value.length === 0) {
            groups.value.push(freshGroup());
        }
    }
}

function isFilled(condition) {
    const shape = valueShape(condition.operator);
    const value = condition.value;

    // Operators like "has any" / "has none" carry no value and are always complete.
    if (shape === 'none') {
        return true;
    }

    if (shape === 'list') {
        return Array.isArray(value) && value.length > 0;
    }

    if (shape === 'pair') {
        return Array.isArray(value) && value.length === 2 && value.every((part) => part !== '' && part != null);
    }

    return value !== '' && value != null;
}

const cleaned = computed(() =>
    groups.value
        .map((group) => ({
            type: group.type,
            conditions: group.conditions.filter((condition) => isFilled(condition)),
        }))
        .filter((group) => group.conditions.length > 0)
);

const canFilter = computed(() => cleaned.value.length > 0);

// POST keeps the filter out of the URL (we don't need shareable search links).
function applyFilter() {
    router.post('/search', { filter: JSON.stringify(cleaned.value) }, { preserveState: false });
}

function clearFilter() {
    groups.value = [freshGroup()];
    router.get('/search');
}
</script>

<template>
    <div class="flex flex-col gap-5">
        <template v-for="(group, groupIndex) in groups" :key="groupIndex">
            <div class="rounded-lg border border-neutral-50">
                <div
                    class="flex items-center gap-3 bg-neutral-25 px-5 py-4"
                    :class="group.type ? 'rounded-t-lg border-b border-neutral-50' : 'rounded-lg'"
                >
                    <span class="shrink-0 whitespace-nowrap text-label uppercase text-neutral-500">Show me</span>
                    <div class="min-w-0 flex-1 sm:w-56 sm:flex-none">
                        <StyledSelect
                            :model-value="group.type"
                            :options="typeOptions"
                            placeholder="Select…"
                            @update:model-value="changeType(group, $event)"
                        />
                    </div>
                </div>

                <div v-if="group.type" class="rounded-b-lg bg-neutral-0 px-5 py-5">
                <div class="flex flex-col gap-3">
                    <div v-for="(condition, conditionIndex) in group.conditions" :key="conditionIndex" class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-3">
                        <span class="text-label uppercase text-neutral-500 sm:w-16 sm:shrink-0">{{ conditionIndex === 0 ? 'Where' : 'And' }}</span>

                        <div class="w-full sm:w-56 sm:shrink-0">
                            <FieldPicker
                                :fields="fieldsOf(group.type)"
                                :model-value="condition.field"
                                @update:model-value="changeField(condition, group.type, $event)"
                            />
                        </div>

                        <div class="w-full sm:w-40 sm:shrink-0">
                            <StyledSelect
                                :model-value="condition.operator"
                                :options="operatorOptions(group.type, condition.field)"
                                @update:model-value="changeOperator(condition, $event)"
                            />
                        </div>

                        <div v-if="valueShape(condition.operator) !== 'none'" class="w-full min-w-0 sm:flex-1">
                            <FilterValue
                                v-model="condition.value"
                                :data-type="fieldDef(group.type, condition.field)?.dataType"
                                :operator="condition.operator"
                                :options="fieldDef(group.type, condition.field)?.options"
                                :prefix="fieldDef(group.type, condition.field)?.prefix"
                                :suffix="fieldDef(group.type, condition.field)?.suffix"
                            />
                        </div>
                        <div v-else class="hidden sm:block sm:flex-1"></div>

                        <button
                            type="button"
                            class="-mt-1 self-end rounded-md p-2 text-neutral-500 transition-colors hover:text-accent-500 sm:mt-0 sm:shrink-0 sm:self-auto"
                            aria-label="Remove condition"
                            @click="removeCondition(group, conditionIndex)"
                        >
                            <Icon :icon="Delete02Icon" class="size-4" />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-neutral-100 px-3 py-1.5 text-label uppercase text-neutral-700 transition-colors hover:border-accent-500 hover:text-accent-500"
                    @click="addCondition(group)"
                >
                    <Icon :icon="PlusSignIcon" class="size-3.5" /> Add
                </button>
                </div>
            </div>

            <!-- Insert another OR group at this position. -->
            <div class="flex items-center gap-3">
                <span class="or-neutral-100 flex-1" />
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-label uppercase text-accent-500 transition-colors hover:underline focus-visible:underline focus-visible:outline-none"
                    @click="addGroupAt(groupIndex + 1)"
                >
                    <Icon :icon="PlusSignIcon" class="size-3.5" /> Or
                </button>
                <span class="or-neutral-100 flex-1" />
            </div>
        </template>

        <div class="mt-2 flex items-center gap-4">
            <Button variant="primary" size="lg" :disabled="!canFilter" @click="applyFilter">Filter</Button>
            <button type="button" class="text-meta text-neutral-500 transition-colors hover:text-neutral-900" @click="clearFilter">
                Clear filters
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Dashed divider with a larger dash + gap than CSS dotted/dashed borders allow. */
.or-neutral-100 {
    height: 2px;
    background: repeating-linear-gradient(to right, var(--color-neutral-100) 0 8px, transparent 8px 16px);
}
</style>
