<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import TimelineFeed from './TimelineFeed.vue';
import { relativeDay } from '../../lib/format.js';

const props = defineProps({
    label: { type: String, required: true },
    href: { type: String, default: null },
    date: { type: String, default: null }, // yyyy-mm-dd
    items: { type: Array, default: () => [] },
    // Heading rank for the date label: 2 when DateGroup sits directly under the
    // page's own h1 (Timeline, Tag, Search, Archive), 3 when nested inside a
    // SectionHead-led <section> so the outline steps down a level instead of
    // colliding with SectionHead's own h2.
    headingLevel: { type: [String, Number], default: 2 },
});

// Computed client-side so a cached page never shows a stale "Today".
const relative = computed(() => relativeDay(props.date));

const isToday = computed(() => relative.value === 'Today');
const displayLabel = computed(() => relative.value ?? props.label);
// The tag name for the date heading, so callers can pass a numeric or string level.
const headingTag = computed(() => `h${props.headingLevel}`);
</script>

<template>
    <section>
        <component :is="headingTag" class="mb-6 flex items-center gap-2.5 font-display text-item-title">
            <component :is="href ? Link : 'span'" :href="href || undefined" class="transition-colors" :class="href ? 'underline-offset-4 hover:text-accent-500 hover:underline focus-visible:text-accent-500 focus-visible:underline' : ''">
                <time v-if="date" :datetime="date">{{ displayLabel }}</time>
                <template v-else>{{ displayLabel }}</template>
            </component>
            <span v-if="isToday" class="relative flex size-2.5 shrink-0" aria-hidden="true">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-accent-500 opacity-75" />
                <span class="relative inline-flex size-2.5 rounded-full bg-accent-500" />
            </span>
        </component>
        <TimelineFeed :items="items" />
    </section>
</template>
