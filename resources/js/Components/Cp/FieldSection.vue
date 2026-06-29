<script setup>
import Card from '../Ui/Card.vue';
import FieldRenderer from '../Fields/FieldRenderer.vue';

defineProps({
    section: { type: Object, required: true }, // { title, fields: [...] }
    form: { type: Object, required: true },
});
</script>

<template>
    <Card variant="elevated" class="flex flex-col gap-6 p-6">
        <p v-if="section.title" class="text-caption font-semibold text-neutral-900">{{ section.title }}</p>
        <FieldRenderer
            v-for="field in section.fields"
            :key="field.key"
            :field="field"
            :error="form.errors[field.key]"
            :model-value="form[field.key]"
            @update:model-value="form[field.key] = $event"
        />
    </Card>
</template>
