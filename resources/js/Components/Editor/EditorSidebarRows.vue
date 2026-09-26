<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { fieldRows } from '../../lib/editor/placement.js';
import { summariseFields, summariseValue } from '../../lib/editor/summary.js';
import Icon from '../Ui/Icon.vue';
import FieldInput from './FieldInput.vue';
import TimezoneField from './TimezoneField.vue';

/**
 * The sidebar fields on a phone: one collapsed row each, label and value, opening
 * in place to the same input the desktop list draws. One row is open at a time.
 */
const props = defineProps({
    fields: { type: Array, required: true },
    // The editor's form, read for values and errors.
    form: { type: Object, required: true },
    // Extra FieldInput props for one field, e.g. a slug's preview and lock.
    bindings: { type: Function, default: () => ({}) },
});

defineEmits(['update', 'fill', 'preview']);

// Suffixed so no id repeats one in the desktop list, which stays in the DOM (hidden) at this width.
const ID_SUFFIX = '-row';

const rows = computed(() => fieldRows(props.fields, { pairZones: true }));

const openKey = ref(null);

const bodies = ref({});

/** Every field a row edits, including a timezone riding on a date. */
function fieldsOf(row) {
    if (row.kind === 'group') {
        return row.fields;
    }

    return row.zone ? [row.field, row.zone] : [row.field];
}

const isInvalid = (row) => fieldsOf(row).some((field) => props.form.errors[field.name]);

/** The row's value on one line; a date row reads as the date alone. */
function summaryOf(row) {
    return row.kind === 'group'
        ? summariseFields(row.fields, props.form)
        : summariseValue(row.field, props.form[row.field.name]);
}

/** Open a row closing any other, or close it again; opening puts the cursor in its input. */
async function toggle(row) {
    openKey.value = openKey.value === row.key ? null : row.key;

    if (openKey.value === null) {
        return;
    }

    await nextTick();

    bodies.value[row.key]?.querySelector('input, textarea, select, button')?.focus();
}

// A refused field cannot be fixed while its row is shut, so the first one opens.
watch(() => Object.keys(props.form.errors).join(','), () => {
    const current = rows.value.find((row) => row.key === openKey.value);

    if (current && isInvalid(current)) {
        return;
    }

    openKey.value = rows.value.find(isInvalid)?.key ?? openKey.value;
}, { immediate: true });
</script>

<template>
    <div class="divide-y divide-neutral-50 border-y border-neutral-50">
        <div v-for="(row, index) in rows" :key="row.key">
            <button
                type="button"
                class="flex min-h-12 w-full items-center gap-3 py-3 text-left"
                :aria-expanded="openKey === row.key"
                :aria-controls="`sidebar-row-${index}`"
                @click="toggle(row)"
            >
                <span class="shrink-0 text-sm font-medium" :class="isInvalid(row) ? 'text-red-600' : 'text-neutral-900'">
                    {{ row.kind === 'group' ? row.label : row.field.label }}
                </span>

                <span class="min-w-0 flex-1 truncate text-right text-sm text-neutral-500">
                    {{ openKey === row.key ? '' : summaryOf(row) || 'Not set' }}
                </span>

                <Icon
                    name="ArrowDown01Icon"
                    class="size-4 shrink-0 text-neutral-500 transition-transform"
                    :class="openKey === row.key ? 'rotate-180' : ''"
                />
            </button>

            <div
                v-if="openKey === row.key"
                :id="`sidebar-row-${index}`"
                :ref="(element) => { bodies[row.key] = element; }"
                class="pb-4"
            >
                <div v-if="row.kind === 'field'">
                    <!-- The row header names the field, so the label is only for assistive tech. -->
                    <label v-if="row.field.type !== 'boolean'" :for="`${row.field.name}${ID_SUFFIX}`" class="sr-only">{{ row.field.label }}</label>

                    <FieldInput
                        :id="`${row.field.name}${ID_SUFFIX}`"
                        :field="row.field"
                        :model-value="form[row.field.name]"
                        :error="form.errors[row.field.name]"
                        v-bind="bindings(row.field)"
                        hide-label
                        @update:model-value="$emit('update', row.field, $event)"
                        @fill="(values, options) => $emit('fill', values, options)"
                        @preview="$emit('preview', $event)"
                    />

                    <TimezoneField
                        v-if="row.zone"
                        :id="`${row.zone.name}${ID_SUFFIX}`"
                        :field="row.zone"
                        :model-value="form[row.zone.name]"
                        :error="form.errors[row.zone.name]"
                        :readonly="bindings(row.zone).readonly"
                        class="mt-1"
                        @update:model-value="$emit('update', row.zone, $event)"
                        @fill="(values, options) => $emit('fill', values, options)"
                    />
                </div>

                <div v-else class="space-y-6">
                    <FieldInput
                        v-for="field in row.fields"
                        :id="`${field.name}${ID_SUFFIX}`"
                        :key="field.name"
                        :field="field"
                        :model-value="form[field.name]"
                        :error="form.errors[field.name]"
                        v-bind="bindings(field)"
                        @update:model-value="$emit('update', field, $event)"
                        @fill="(values, options) => $emit('fill', values, options)"
                        @preview="$emit('preview', $event)"
                    />
                </div>
            </div>
        </div>
    </div>
</template>
