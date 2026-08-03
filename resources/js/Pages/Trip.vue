<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    title: { type: String, required: true },
    days: { type: Number, required: true },
    start: { type: String, required: true },
    end: { type: String, required: true },
    // [{ name, slug }]
    tags: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Trips', href: '/trips' }, { label: props.title }],
});

// "5 Aug 2026 to 12 Aug 2026, 8 days", collapsing to a single date for a
// day trip where both ends land on the same day.
const summary = computed(() => {
    const range = props.start === props.end ? props.start : `${props.start} to ${props.end}`;

    return `${range}, ${props.days} ${props.days === 1 ? 'day' : 'days'}`;
});

// The number of entries gathered by the window, across every day group.
const entryCount = computed(() =>
    props.groups.reduce((total, group) => total + group.items.length, 0),
);
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">{{ title }}</h1>
        <p class="mt-2 text-meta text-neutral-500">{{ summary }}</p>

        <!-- Same "Tagged #slug" treatment EntryFooter gives entry tags, so a
             trip's tags read identically to tags anywhere else on the site. -->
        <p v-if="tags.length" class="mt-2 text-caption text-neutral-500">
            Tagged
            <template v-for="(tag, index) in tags" :key="tag.slug"><Link :href="`/tags/${tag.slug}`" class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">#{{ tag.slug }}</Link><span v-if="index < tags.length - 1">, </span></template>
        </p>
    </header>

    <template v-if="groups.length">
        <p class="mt-8 text-meta text-neutral-500">
            {{ entryCount }} {{ entryCount === 1 ? 'entry' : 'entries' }} from this trip
        </p>

        <div class="mt-6 flex flex-col gap-14">
            <DateGroup
                v-for="group in groups"
                :key="group.label"
                :label="group.label"
                :date="group.date"
                :href="group.href"
                :items="group.items"
            />
        </div>
    </template>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing logged during this trip.</p>
</template>
