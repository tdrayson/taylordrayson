<script setup>
import { computed } from 'vue';
import { router, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import QueryBuilder from '../Components/Search/QueryBuilder.vue';
import OptionCard from '../Components/Ui/OptionCard.vue';
import StyledSelect from '../Components/Search/StyledSelect.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import Pagination from '../Components/Ui/Pagination.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    schema: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
    filter: { type: Array, default: () => [] },
    order: { type: String, default: 'newest' },
    groups: { type: Array, default: () => [] },
    total: { type: Number, default: 0 },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

const orderOptions = [
    { value: 'newest', label: 'Newest first' },
    { value: 'oldest', label: 'Oldest first' },
];

setLayoutProps({
    breadcrumb: [{ label: 'Search' }],
});

const hasSearch = computed(() => props.filter.length > 0);

// Show the examples when there's nothing to look at yet: the empty page, or a
// search that came back with no results.
const showExamples = computed(() => !hasSearch.value || props.total === 0);

// Run a ready-made example: drop its filter in and search, reusing the normal
// flow so the builder repopulates and results appear.
function runExample(preset) {
    router.post('/search', { filter: JSON.stringify(preset.filter) }, { preserveState: false });
}

// Re-run the committed search in a new order, keeping it out of the URL.
function changeOrder(order) {
    router.post('/search', { filter: JSON.stringify(props.filter), order }, { preserveScroll: true });
}

// Re-run the committed search for another page, preserving the chosen order.
function goToPage(page) {
    router.post('/search', { filter: JSON.stringify(props.filter), order: props.order, page }, { preserveScroll: false });
}
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">Search</h1>
        <p class="mt-2 max-w-prose text-meta text-neutral-500">
            Dig through everything I've logged. Stack a few conditions to get specific, or start from an example below.
        </p>
    </header>

    <QueryBuilder :key="JSON.stringify(filter)" class="mt-8" :schema="schema" :filter="filter" />

    <template v-if="hasSearch">
        <div class="mt-10 flex items-center justify-between gap-4">
            <p class="text-caption text-neutral-500">{{ total }} {{ total === 1 ? 'result' : 'results' }}</p>
            <div v-if="groups.length" class="w-40 shrink-0">
                <StyledSelect
                    :model-value="order"
                    :options="orderOptions"
                    aria-label="Sort results"
                    @update:model-value="changeOrder"
                />
            </div>
        </div>

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

        <p v-else class="mt-6 text-meta text-neutral-500">Nothing matched. Try one of the examples below.</p>

        <Pagination
            v-if="lastPage > 1"
            class="mt-14"
            :current-page="currentPage"
            :last-page="lastPage"
            @navigate="goToPage"
        />
    </template>

    <section v-if="showExamples && presets.length" class="mt-12">
        <h2 class="mb-3 text-label uppercase text-neutral-500">
            {{ hasSearch ? 'Try one of these instead' : 'Not sure where to start?' }}
        </h2>
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <OptionCard
                v-for="preset in presets"
                :key="preset.key"
                :label="preset.label"
                :description="preset.description"
                @select="runExample(preset)"
            />
        </div>
    </section>
</template>
