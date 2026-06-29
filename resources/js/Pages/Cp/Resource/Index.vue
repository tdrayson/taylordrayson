<script setup>
import { ref, watch } from 'vue';
import { router, Link, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import Button from '../../../Components/Ui/Button.vue';
import Input from '../../../Components/Ui/Input.vue';
import Card from '../../../Components/Ui/Card.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    resource: { type: Object, required: true },
    records: { type: Object, required: true },
    filters: { type: Object, required: true },
});

setLayoutProps({ mode: 'cp' });

const search = ref(props.filters.search ?? '');
let timer = null;

watch(search, (value) => {
    clearTimeout(timer);
    timer = setTimeout(() => {
        router.get(`/cp/${props.resource.slug}`, { search: value }, { preserveState: true, replace: true });
    }, 250);
});

function cell(record, key) {
    const value = record[key];

    return value === null || value === undefined || value === '' ? 'Not set' : value;
}

function remove(id) {
    if (window.confirm('Delete this record?')) {
        router.delete(`/cp/${props.resource.slug}/${id}`);
    }
}
</script>

<template>
    <AppHead :og="{ title: resource.pluralLabel }" />

    <div class="flex items-center justify-between gap-4">
        <h1 class="font-display text-display">{{ resource.pluralLabel }}</h1>
        <Button :href="`/cp/${resource.slug}/create`" variant="primary">New</Button>
    </div>

    <div v-if="resource.searchable.length" class="mt-6 max-w-sm">
        <Input v-model="search" placeholder="Search" />
    </div>

    <Card variant="outline" class="mt-6 overflow-x-auto p-0">
        <table class="w-full text-meta">
            <thead>
                <tr class="border-b border-neutral-50 text-left text-label text-neutral-500">
                    <th v-for="column in resource.columns" :key="column.key" class="px-4 py-3 font-medium">{{ column.label }}</th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in records.data" :key="record.id" class="border-b border-neutral-25 last:border-0 hover:bg-neutral-25">
                    <td v-for="column in resource.columns" :key="column.key" class="px-4 py-3 text-neutral-900">
                        <Link :href="`/cp/${resource.slug}/${record.id}/edit`" class="block">{{ cell(record, column.key) }}</Link>
                    </td>
                    <td class="px-4 py-3 text-right">
                        <button type="button" class="text-label text-red-600 hover:text-red-700" @click="remove(record.id)">Delete</button>
                    </td>
                </tr>
                <tr v-if="records.data.length === 0">
                    <td :colspan="resource.columns.length + 1" class="px-4 py-8 text-center text-neutral-500">No records yet.</td>
                </tr>
            </tbody>
        </table>
    </Card>

    <div v-if="records.links" class="mt-6 flex flex-wrap gap-1">
        <Link
            v-for="link in records.links"
            :key="link.label"
            :href="link.url ?? ''"
            class="rounded-md px-3 py-1.5 text-label"
            :class="[link.active ? 'bg-accent-500 text-white' : 'text-neutral-700 hover:bg-neutral-25', !link.url && 'pointer-events-none opacity-40']"
            v-html="link.label"
        />
    </div>
</template>
