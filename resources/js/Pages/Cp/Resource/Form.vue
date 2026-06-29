<script setup>
import { computed } from 'vue';
import { useForm, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import EditorShell from '../../../Components/Cp/EditorShell.vue';
import FieldSection from '../../../Components/Cp/FieldSection.vue';
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
const heading = computed(() => (isEdit.value ? props.values.title || props.values.name || `Edit ${props.resource.label.toLowerCase()}` : `New ${props.resource.label.toLowerCase()}`));

const tabs = computed(() => props.resource.layout?.tabs ?? ['Main']);
const sections = computed(() => props.resource.layout?.sections ?? []);

function sectionsFor(area, active) {
    return sections.value.filter((section) => section.area === area && (section.tab ?? 'Main') === active);
}

/** A status dot reflects draft state where the resource has a `draft` field. */
const hasDraft = computed(() => sections.value.some((s) => s.fields.some((f) => f.key === 'draft')));
const statusClass = computed(() => {
    if (!hasDraft.value) {
        return 'bg-neutral-300';
    }

    return form.draft ? 'bg-amber-400' : 'bg-green-500';
});

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

    <form @submit.prevent="submit">
        <EditorShell :tabs="tabs">
            <template #status>
                <span class="size-2.5 shrink-0 rounded-full" :class="statusClass" />
            </template>
            <template #heading>{{ heading }}</template>
            <template #actions>
                <Button :href="`/cp/${resource.slug}`" variant="secondary">Cancel</Button>
                <Button type="submit" variant="primary" :disabled="form.processing">Save</Button>
            </template>

            <template #main="{ active }">
                <FieldSection
                    v-for="(section, index) in sectionsFor('main', active)"
                    :key="`main-${index}`"
                    :section="section"
                    :form="form"
                />
            </template>

            <template #sidebar="{ active }">
                <FieldSection
                    v-for="(section, index) in sectionsFor('sidebar', active)"
                    :key="`sidebar-${index}`"
                    :section="section"
                    :form="form"
                />
            </template>
        </EditorShell>
    </form>
</template>
