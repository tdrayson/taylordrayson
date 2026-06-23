<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Delete02Icon, PlusSignIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Icon.vue';
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
    on: 'on', not_on: 'not on', in: 'is', not_in: 'is not', before: 'before', after: 'after',
};

const schemaByType = computed(() => Object.fromEntries(props.schema.map((type) => [type.type, type])));
const typeOptions = computed(() => props.schema.map((type) => ({ value: type.type, label: type.label })));

const fieldsOf = (type) => schemaByType.value[type]?.fields ?? [];
const fieldDef = (type, key) => fieldsOf(type).find((field) => field.key === key) ?? null;
const operatorOptions = (type, key) =>
    (fieldDef(type, key)?.operators ?? []).map((operator) => ({ value: operator, label: OPERATOR_LABELS[operator] ?? operator }));

// The value shape an operator expects: a [from, to] pair, a multi-select list, or a scalar.
function valueShape(operator) {
    if (operator === 'between' || operator === 'not_between') {
        return 'pair';
    }

    if (operator === 'is' || operator === 'is_not') {
        return 'list';
    }

    return 'single';
}

const blankValue = (operator) => (valueShape(operator) === 'pair' ? ['', ''] : valueShape(operator) === 'list' ? [] : '');

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
            <div class="rounded-lg border border-line-2">
                <div
                    class="flex items-center gap-3 bg-surface px-5 py-4"
                    :class="group.type ? 'rounded-t-lg border-b border-line-2' : 'rounded-lg'"
                >
                    <span class="shrink-0 whitespace-nowrap text-label uppercase text-ink-3">Show me</span>
                    <div class="w-56">
                        <StyledSelect
                            :model-value="group.type"
                            :options="typeOptions"
                            placeholder="Select…"
                            @update:model-value="changeType(group, $event)"
                        />
                    </div>
                </div>

                <div v-if="group.type" class="rounded-b-lg bg-canvas px-5 py-5">
                <div class="flex flex-col gap-3">
                    <div v-for="(condition, conditionIndex) in group.conditions" :key="conditionIndex" class="flex items-center gap-3">
                        <span class="w-16 shrink-0 text-label uppercase text-ink-3">{{ conditionIndex === 0 ? 'Where' : 'And' }}</span>

                        <div class="w-56 shrink-0">
                            <FieldPicker
                                :fields="fieldsOf(group.type)"
                                :model-value="condition.field"
                                @update:model-value="changeField(condition, group.type, $event)"
                            />
                        </div>

                        <div class="w-40 shrink-0">
                            <StyledSelect
                                :model-value="condition.operator"
                                :options="operatorOptions(group.type, condition.field)"
                                @update:model-value="changeOperator(condition, $event)"
                            />
                        </div>

                        <div class="min-w-0 flex-1">
                            <FilterValue
                                v-model="condition.value"
                                :data-type="fieldDef(group.type, condition.field)?.dataType"
                                :operator="condition.operator"
                                :options="fieldDef(group.type, condition.field)?.options"
                                :prefix="fieldDef(group.type, condition.field)?.prefix"
                                :suffix="fieldDef(group.type, condition.field)?.suffix"
                            />
                        </div>

                        <button
                            type="button"
                            class="shrink-0 rounded-md p-2 text-ink-3 transition-colors hover:text-accent"
                            aria-label="Remove condition"
                            @click="removeCondition(group, conditionIndex)"
                        >
                            <Icon :icon="Delete02Icon" class="size-4" />
                        </button>
                    </div>
                </div>

                <button
                    type="button"
                    class="mt-4 inline-flex items-center gap-1.5 rounded-md border border-line px-3 py-1.5 text-label uppercase text-ink-2 transition-colors hover:border-accent hover:text-accent"
                    @click="addCondition(group)"
                >
                    <Icon :icon="PlusSignIcon" class="size-3.5" /> Add
                </button>
                </div>
            </div>

            <!-- Insert another OR group at this position. -->
            <div class="flex items-center gap-3">
                <span class="or-line flex-1" />
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 rounded-md px-3 py-1.5 text-label uppercase text-accent transition-colors hover:underline focus-visible:underline focus-visible:outline-none"
                    @click="addGroupAt(groupIndex + 1)"
                >
                    <Icon :icon="PlusSignIcon" class="size-3.5" /> Or
                </button>
                <span class="or-line flex-1" />
            </div>
        </template>

        <div class="mt-2 flex items-center gap-4">
            <button
                type="button"
                class="rounded-md bg-accent px-5 py-2.5 text-meta font-medium text-canvas transition-colors hover:bg-accent-active disabled:opacity-40"
                :disabled="!canFilter"
                @click="applyFilter"
            >
                Filter
            </button>
            <button type="button" class="text-meta text-ink-3 transition-colors hover:text-ink" @click="clearFilter">
                Clear filters
            </button>
        </div>
    </div>
</template>

<style scoped>
/* Dashed divider with a larger dash + gap than CSS dotted/dashed borders allow. */
.or-line {
    height: 2px;
    background: repeating-linear-gradient(to right, var(--color-line) 0 8px, transparent 8px 16px);
}
</style>
