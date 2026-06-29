<script setup>
import FieldRenderer from '../Fields/FieldRenderer.vue';

defineProps({
    section: { type: Object, required: true }, // { title, fields: [...] }
    form: { type: Object, required: true },
    // A divider above the section when it follows another inside the same card.
    divided: { type: Boolean, default: false },
    // Lay fields out in a dense two-column grid (main area) vs a single
    // stacked column (narrow sidebar).
    grid: { type: Boolean, default: false },
});

// Long-form / composite fields always take the full row; everything else can
// sit two-up in the grid.
const WIDE = ['textarea', 'editor', 'json', 'group', 'keyvalue', 'tags'];

function cellClass(field) {
    return WIDE.includes(field.type) ? 'sm:col-span-2' : '';
}
</script>

<template>
    <section :class="divided ? 'border-t border-neutral-50 pt-6' : ''">
        <p v-if="section.title" class="mb-5 text-caption font-semibold uppercase tracking-wide text-neutral-500">{{ section.title }}</p>
        <div :class="grid ? 'grid grid-cols-1 gap-x-5 gap-y-5 sm:grid-cols-2' : 'flex flex-col gap-5'">
            <div v-for="field in section.fields" :key="field.key" :class="grid ? cellClass(field) : ''">
                <FieldRenderer
                    :field="field"
                    :error="form.errors[field.key]"
                    :model-value="form[field.key]"
                    @update:model-value="form[field.key] = $event"
                />
            </div>
        </div>
    </section>
</template>
