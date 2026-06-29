<script setup>
import { computed, ref, watch } from 'vue';
import { router, Link, setLayoutProps } from '@inertiajs/vue3';
import { ArrowUp01Icon, ArrowDown01Icon } from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../../../Layouts/AppLayout.vue';
import AppHead from '../../../Components/AppHead.vue';
import Button from '../../../Components/Ui/Button.vue';
import Input from '../../../Components/Ui/Input.vue';
import Card from '../../../Components/Ui/Card.vue';
import Icon from '../../../Components/Ui/Icon.vue';
import { duration } from '../../../lib/format.js';

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

const hasDraft = computed(() => (props.resource.fields ?? []).some((field) => field.key === 'draft'));

// Field type per column, so a cell can be formatted (dates, durations) rather
// than dumping the raw stored value.
const fieldType = computed(() => Object.fromEntries((props.resource.fields ?? []).map((field) => [field.key, field.type])));

const MONTHS = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

// Format the stored wall-clock string by slicing its parts, never via new Date()
// (which would re-interpret it in the viewer's browser timezone).
function formatDate(value) {
    const [y, m, d] = String(value).slice(0, 10).split('-');

    return `${Number(d)} ${MONTHS[Number(m) - 1] ?? ''} ${y}`;
}

function formatDateTime(value) {
    return `${formatDate(value)}, ${String(value).slice(11, 16)}`;
}

function cell(record, key) {
    const value = record[key];

    if (value === null || value === undefined || value === '') {
        return 'Not set';
    }

    if (key === 'duration') {
        return duration(Number(value));
    }

    switch (fieldType.value[key]) {
        case 'datetime':
            return formatDateTime(value);
        case 'date':
            return formatDate(value);
        default:
            return value;
    }
}

function sortBy(key) {
    const direction = props.filters.sort === key && props.filters.direction === 'asc' ? 'desc' : 'asc';
    router.get(`/cp/${props.resource.slug}`, { search: search.value, sort: key, direction }, { preserveState: true, replace: true });
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
        <h1 class="text-item-title text-neutral-900">{{ resource.pluralLabel }}</h1>
        <Button :href="`/cp/${resource.slug}/create`" variant="primary">Create entry</Button>
    </div>

    <div v-if="resource.searchable.length" class="mt-6 max-w-sm">
        <Input v-model="search" placeholder="Search" />
    </div>

    <Card variant="elevated" class="mt-6 overflow-x-auto p-0">
        <table class="w-full text-meta">
            <thead>
                <tr class="border-b border-neutral-50 bg-neutral-25 text-left text-caption font-medium text-neutral-500">
                    <th v-for="column in resource.columns" :key="column.key" class="px-4 py-3 font-medium">
                        <button type="button" class="flex items-center gap-1 hover:text-neutral-900" @click="sortBy(column.key)">
                            {{ column.label }}
                            <Icon
                                v-if="filters.sort === column.key"
                                :icon="filters.direction === 'asc' ? ArrowUp01Icon : ArrowDown01Icon"
                                class="size-3"
                            />
                        </button>
                    </th>
                    <th class="px-4 py-3" />
                </tr>
            </thead>
            <tbody>
                <tr v-for="record in records.data" :key="record.id" class="border-b border-neutral-25 last:border-0 hover:bg-neutral-25">
                    <td v-for="(column, index) in resource.columns" :key="column.key" class="px-4 py-3 text-neutral-900">
                        <Link :href="`/cp/${resource.slug}/${record.id}/edit`" class="flex items-center gap-2">
                            <span
                                v-if="index === 0 && hasDraft"
                                class="size-2 shrink-0 rounded-full"
                                :class="record.draft ? 'bg-amber-400' : 'bg-green-500'"
                            />
                            <span>{{ cell(record, column.key) }}</span>
                        </Link>
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

    <div class="mt-4 flex items-center justify-between gap-4">
        <p v-if="records.total" class="text-caption text-neutral-500">{{ records.from }}-{{ records.to }} of {{ records.total }}</p>
        <div v-if="records.links" class="flex flex-wrap gap-1">
            <Link
                v-for="link in records.links"
                :key="link.label"
                :href="link.url ?? ''"
                class="rounded-md px-3 py-1.5 text-label"
                :class="[link.active ? 'bg-accent-500 text-neutral-0' : 'text-neutral-700 hover:bg-neutral-25', !link.url && 'pointer-events-none opacity-40']"
                v-html="link.label"
            />
        </div>
    </div>
</template>
