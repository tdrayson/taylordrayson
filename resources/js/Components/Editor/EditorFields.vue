<script setup>
import { computed } from 'vue';
import FieldGroup from './FieldGroup.vue';
import FieldInput from './FieldInput.vue';

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
});

defineEmits(['update', 'fill', 'preview']);

/**
 * Rows in declaration order. Drawing every group after every loose field
 * instead put an address a whole form away from the venue lookup that fills it.
 */
const rows = computed(() => {
    const seen = new Set();

    return props.fields.flatMap((field) => {
        if (! field.group) {
            return [{ kind: 'field', key: field.name, field }];
        }

        if (seen.has(field.group)) {
            return [];
        }

        seen.add(field.group);

        return [{
            kind: 'group',
            key: field.group,
            label: field.group,
            fields: props.fields.filter((candidate) => candidate.group === field.group),
        }];
    });
});

/** The group's set values on one line, so it reads without being opened. */
function groupSummary(row) {
    return row.fields
        .map((field) => props.form[field.name])
        .filter((value) => value !== null && value !== undefined && value !== '')
        .join(', ');
}
</script>

<template>
    <div class="space-y-6">
        <template v-for="row in rows" :key="row.key">
            <!-- Wrapped so anything slotted after a field hangs off it rather
                 than becoming another row in the stack's spacing. -->
            <div v-if="row.kind === 'field'">
                <FieldInput
                    :field="row.field"
                    :model-value="form[row.field.name]"
                    :error="form.errors[row.field.name]"
                    v-bind="bindings(row.field)"
                    @update:model-value="$emit('update', row.field, $event)"
                    @fill="(values, options) => $emit('fill', values, options)"
                    @preview="$emit('preview', $event)"
                />

                <slot name="after-field" :field="row.field" />
            </div>

            <FieldGroup
                v-else
                :label="row.label"
                :summary="groupSummary(row)"
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
