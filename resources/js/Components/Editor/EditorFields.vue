<script setup>
import { computed } from 'vue';
import { fieldRows, isTimezone } from '../../lib/editor/placement.js';
import { summariseFields } from '../../lib/editor/summary.js';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';
import TimezoneField from './TimezoneField.vue';

/**
 * A stack of field rows, a group standing where its first field was declared.
 * Values are read off `form`; every change goes back up through `update`.
 */
const props = defineProps({
    fields: { type: Array, required: true },
    // The editor's form, read for values and errors.
    form: { type: Object, required: true },
    // Extra FieldInput props for one field, e.g. a slug's preview and lock.
    bindings: { type: Function, default: () => ({}) },
    // Draws a timezone as its value and a Change link, under the date it follows when there is one.
    compactZones: { type: Boolean, default: false },
});

defineEmits(['update', 'fill', 'preview']);

const rows = computed(() => fieldRows(props.fields, { pairZones: props.compactZones }));
</script>

<template>
    <div class="space-y-6">
        <template v-for="row in rows" :key="row.key">
            <!-- Wrapped so anything slotted after a field hangs off it rather
                 than becoming another row in the stack's spacing. -->
            <div v-if="row.kind === 'field'">
                <TimezoneField
                    v-if="compactZones && isTimezone(row.field)"
                    :field="row.field"
                    :model-value="form[row.field.name]"
                    :error="form.errors[row.field.name]"
                    :readonly="bindings(row.field).readonly"
                    labelled
                    @update:model-value="$emit('update', row.field, $event)"
                    @fill="(values, options) => $emit('fill', values, options)"
                />

                <FieldInput
                    v-else
                    :field="row.field"
                    :model-value="form[row.field.name]"
                    :error="form.errors[row.field.name]"
                    v-bind="bindings(row.field)"
                    @update:model-value="$emit('update', row.field, $event)"
                    @fill="(values, options) => $emit('fill', values, options)"
                    @preview="$emit('preview', $event)"
                />

                <TimezoneField
                    v-if="row.zone"
                    :field="row.zone"
                    :model-value="form[row.zone.name]"
                    :error="form.errors[row.zone.name]"
                    :readonly="bindings(row.zone).readonly"
                    class="mt-1"
                    @update:model-value="$emit('update', row.zone, $event)"
                    @fill="(values, options) => $emit('fill', values, options)"
                />

                <slot name="after-field" :field="row.field" />
            </div>

            <FieldGroup
                v-else
                :label="row.label"
                :summary="summariseFields(row.fields, form)"
                :invalid="row.fields.some((field) => form.errors[field.name])"
            >
                <FieldInput
                    v-for="field in row.fields"
                    :key="field.name"
                    :field="field"
                    :model-value="form[field.name]"
                    :error="form.errors[field.name]"
                    v-bind="bindings(field)"
                    @update:model-value="$emit('update', field, $event)"
                    @fill="(values, options) => $emit('fill', values, options)"
                    @preview="$emit('preview', $event)"
                />
            </FieldGroup>
        </template>
    </div>
</template>
