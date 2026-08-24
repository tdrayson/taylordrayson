<script setup>
import { computed } from 'vue';
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    title: { type: String, required: true },
    days: { type: Number, required: true },
    // { day, month, year, time, label, iso, offset }, formatted server-side in
    // the trip's own timezone.
    start: { type: Object, required: true },
    end: { type: Object, required: true },
    // [{ name, slug, url }]
    tags: { type: Array, default: () => [] },
    groups: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Trips', href: '/trips' }, { label: props.title }],
});

// "6 days", the span the window covers counting both end days.
const dayCount = computed(() => `${props.days} ${props.days === 1 ? 'day' : 'days'}`);
</script>

<template>
    <AppHead :og="og" />

    <header>
        <h1 class="font-display text-display">{{ title }}</h1>
        <!-- The full window, both ends spelled out with their time, then the span
             in brackets the way a multi-day event card reports its own. -->
        <p class="mt-2 text-meta text-neutral-500">
            <time :datetime="start.iso">{{ start.label }}</time> to <time :datetime="end.iso">{{ end.label }}</time> <span class="text-neutral-400 tnum">({{ dayCount }})</span>
        </p>

        <!-- Same treatment EntryFooter gives entry tags, so a trip's tags read
             identically to tags anywhere else on the site. -->
        <p v-if="tags.length" class="mt-2 text-caption text-neutral-500">
            Tagged
            <template v-for="(tag, index) in tags" :key="tag.slug"><Link :href="tag.url" class="font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ tag.name }}</Link><span v-if="index < tags.length - 1">, </span></template>
        </p>
    </header>

    <template v-if="groups.length">
        <div class="h-feed mt-10 flex flex-col gap-14">
            <AuthorRef />
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
