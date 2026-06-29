<script setup>
import FieldRenderer from '../Fields/FieldRenderer.vue';

defineProps({
    section: { type: Object, required: true }, // { title, fields: [...] }
    form: { type: Object, required: true },
    // A divider above the section when it follows another inside the same card.
    divided: { type: Boolean, default: false },
});
</script>

<template>
    <section class="flex flex-col gap-5" :class="divided ? 'border-t border-neutral-50 pt-6' : ''">
        <p v-if="section.title" class="text-caption font-semibold uppercase tracking-wide text-neutral-500">{{ section.title }}</p>
        <FieldRenderer
            v-for="field in section.fields"
            :key="field.key"
            :field="field"
            :error="form.errors[field.key]"
            :model-value="form[field.key]"
            @update:model-value="form[field.key] = $event"
        />
    </section>
</template>
