<script setup>
import { computed } from 'vue';
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import IntroBlock from '../Components/Timeline/IntroBlock.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import Pagination from '../Components/Ui/Pagination.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    groups: { type: Array, default: () => [] },
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
});

setLayoutProps({
    breadcrumb: [],
});

function pageUrl(page) {
    return page <= 1 ? '/' : `/?page=${page}`;
}

const prevUrl = computed(() => (props.currentPage > 1 ? pageUrl(props.currentPage - 1) : null));
const nextUrl = computed(() => (props.currentPage < props.lastPage ? pageUrl(props.currentPage + 1) : null));
</script>

<template>
    <Head title="Timeline" />

    <IntroBlock v-if="currentPage === 1" class="mb-14" />

    <div v-if="groups.length" class="h-feed flex flex-col gap-14">
        <h1 class="p-name sr-only">Taylor Drayson timeline</h1>
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
        />
    </div>

    <p v-else class="text-meta text-ink-3">No entries yet.</p>

    <Pagination
        v-if="lastPage > 1"
        class="mt-14"
        :current-page="currentPage"
        :last-page="lastPage"
        :prev-url="prevUrl"
        :next-url="nextUrl"
    />
</template>

