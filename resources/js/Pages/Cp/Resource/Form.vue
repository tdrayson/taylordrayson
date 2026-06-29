<script setup>
import { computed } from 'vue';
import { useForm, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import FieldRenderer from '../../../Components/Fields/FieldRenderer.vue';
import Button from '../../../Components/Ui/Button.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    resource: { type: Object, required: true },
    record: { type: Object, default: null },
    values: { type: Object, required: true },
});

setLayoutProps({ mode: 'cp' });

const form = useForm({ ...props.values });

const isEdit = computed(() => props.record !== null);
const heading = computed(() => `${isEdit.value ? 'Edit' : 'New'} ${props.resource.label.toLowerCase()}`);

function submit() {
    if (isEdit.value) {
        form.put(`/cp/${props.resource.slug}/${props.record.id}`);
    } else {
        form.post(`/cp/${props.resource.slug}`);
    }
}
</script>

<template>
    <AppHead :og="{ title: heading }" />
    <h1 class="font-display text-display">{{ heading }}</h1>

    <form class="mt-8 flex max-w-2xl flex-col gap-5" @submit.prevent="submit">
        <FieldRenderer
            v-for="field in resource.fields"
            :key="field.key"
            :field="field"
            :error="form.errors[field.key]"
            :model-value="form[field.key]"
            mode="edit"
            @update:model-value="form[field.key] = $event"
        />
        <div class="flex gap-3 pt-2">
            <Button type="submit" variant="primary" :disabled="form.processing">Save</Button>
            <Button :href="`/cp/${resource.slug}`" variant="secondary">Cancel</Button>
        </div>
    </form>
</template>
