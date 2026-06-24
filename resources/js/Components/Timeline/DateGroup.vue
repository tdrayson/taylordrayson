<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import TimelineFeed from './TimelineFeed.vue';

const props = defineProps({
    label: { type: String, required: true },
    href: { type: String, default: null },
    date: { type: String, default: null }, // yyyy-mm-dd
    items: { type: Array, default: () => [] },
});

function toKey(value) {
    return `${value.getFullYear()}-${String(value.getMonth() + 1).padStart(2, '0')}-${String(value.getDate()).padStart(2, '0')}`;
}

// Relative label computed client-side so a cached page never shows a stale "Today".
const relative = computed(() => {
    if (!props.date) {
        return null;
    }

    const now = new Date();
    const yesterday = new Date(now);
    yesterday.setDate(yesterday.getDate() - 1);

    if (props.date === toKey(now)) {
        return 'Today';
    }

    if (props.date === toKey(yesterday)) {
        return 'Yesterday';
    }

    return null;
});

const isToday = computed(() => relative.value === 'Today');
const displayLabel = computed(() => relative.value ?? props.label);
</script>

<template>
    <section>
        <h2 class="mb-6 flex items-center gap-2.5 font-display text-item-title">
            <span v-if="isToday" class="relative flex size-2.5 shrink-0" aria-hidden="true">
                <span class="absolute inline-flex size-full animate-ping rounded-full bg-accent opacity-75" />
                <span class="relative inline-flex size-2.5 rounded-full bg-accent" />
            </span>
            <component :is="href ? Link : 'span'" :href="href || undefined" class="transition-colors" :class="href ? 'hover:text-accent' : ''">
                <time v-if="date" :datetime="date">{{ displayLabel }}</time>
                <template v-else>{{ displayLabel }}</template>
            </component>
        </h2>
        <TimelineFeed :items="items" />
    </section>
</template>
