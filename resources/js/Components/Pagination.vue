<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    prevUrl: { type: String, default: null },
    nextUrl: { type: String, default: null },
});
</script>

<template>
    <nav aria-label="Pagination" class="flex items-center justify-between border-t border-line-2 pt-6 text-sm font-medium">
        <component
            :is="prevUrl ? Link : 'span'"
            :href="prevUrl || undefined"
            aria-label="Previous page"
            :aria-disabled="prevUrl ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="prevUrl ? 'text-ink-2 transition-colors hover:text-accent' : 'cursor-default text-ink-3/40'"
        >
            <Icon :icon="ArrowLeft01Icon" class="size-4" />
            Previous
        </component>

        <span class="text-ink-3 tnum" aria-current="page">Page {{ currentPage }} of {{ lastPage }}</span>

        <component
            :is="nextUrl ? Link : 'span'"
            :href="nextUrl || undefined"
            aria-label="Next page"
            :aria-disabled="nextUrl ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="nextUrl ? 'text-ink-2 transition-colors hover:text-accent' : 'cursor-default text-ink-3/40'"
        >
            Next
            <Icon :icon="ArrowRight01Icon" class="size-4" />
        </component>
    </nav>
</template>
