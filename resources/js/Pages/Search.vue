<script setup>
import { Head, router, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import QueryBuilder from '../Components/Search/QueryBuilder.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import Pagination from '../Components/Ui/Pagination.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    schema: { type: Array, default: () => [] },
    filter: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

setLayoutProps({
    breadcrumb: [{ label: 'Search' }],
});

const hasFilter = () => props.filter.length > 0;

// Re-run the committed filter for another page, keeping it out of the URL.
function goToPage(page) {
    router.post('/search', { filter: JSON.stringify(props.filter), page }, { preserveScroll: false });
}
</script>

<template>
    <Head title="Search" />

    <header>
        <h1 class="font-display text-display">Search</h1>
        <p class="mt-2 text-meta text-neutral-500">
            Build an advanced filter — pick a type per group, AND conditions within a group, OR between groups.
        </p>
    </header>

    <QueryBuilder :key="JSON.stringify(filter)" class="mt-8" :schema="schema" :filter="filter" />

    <template v-if="hasFilter()">
        <p class="mt-10 text-caption text-neutral-500">{{ total }} {{ total === 1 ? 'result' : 'results' }}</p>

        <div v-if="groups.length" class="mt-6 flex flex-col gap-14">
            <DateGroup
                v-for="group in groups"
                :key="group.label"
                :label="group.label"
                :date="group.date"
                :href="group.href"
                :items="group.items"
            />
        </div>

        <p v-else class="mt-6 text-meta text-neutral-500">No matches for this filter.</p>

        <Pagination
            v-if="lastPage > 1"
            class="mt-14"
            :current-page="currentPage"
            :last-page="lastPage"
            @navigate="goToPage"
        />
    </template>
</template>
