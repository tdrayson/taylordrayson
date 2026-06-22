<script setup>
import { computed } from 'vue';
import { Head, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import ViewHeader from '../Components/ViewHeader.vue';
import NumberStrip from '../Components/NumberStrip.vue';
import CalendarMonth from '../Components/CalendarMonth.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    year: { type: Number, required: true },
    month: { type: Number, required: true },
    entriesCount: { type: Number, default: 0 },
    days: { type: Object, default: () => ({}) },
    stats: { type: Array, default: () => [] },
});

const pad = (value) => String(value).padStart(2, '0');
const date = computed(() => new Date(props.year, props.month - 1, 1));
const monthName = computed(() => date.value.toLocaleDateString('en-GB', { month: 'long' }));
const subtitle = computed(() => `${props.entriesCount} ${props.entriesCount === 1 ? 'entry' : 'entries'} this month`);

function monthUrl(value) {
    return `/${value.getFullYear()}/${pad(value.getMonth() + 1)}`;
}

function monthLabel(value) {
    return value.toLocaleDateString('en-GB', { month: 'long' });
}

const prevMonth = computed(() => new Date(props.year, props.month - 2, 1));
const nextMonth = computed(() => new Date(props.year, props.month, 1));

setLayoutProps({
    breadcrumb: [
        { label: String(props.year), href: `/${props.year}` },
        { label: monthName.value },
    ],
});
</script>

<template>
    <Head :title="`${monthName} ${year}`" />

    <ViewHeader
        :title="`${monthName} ${year}`"
        :subtitle="subtitle"
        :prev="{ label: monthLabel(prevMonth), href: monthUrl(prevMonth) }"
        :next="{ label: monthLabel(nextMonth), href: monthUrl(nextMonth) }"
    />

    <NumberStrip v-if="stats.length" :stats="stats" class="mt-8" />

    <CalendarMonth :year="year" :month="month" :days="days" />
</template>
