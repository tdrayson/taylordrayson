<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

const props = defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    prevUrl: { type: String, default: null },
    nextUrl: { type: String, default: null },
});

const emit = defineEmits(['navigate']);

const hasPrev = computed(() => props.currentPage > 1);
const hasNext = computed(() => props.currentPage < props.lastPage);

// Pages either link via a URL (prevUrl/nextUrl) or emit `navigate` for the
// callers (e.g. POST-driven search) that drive paging without URLs.
const prevTag = computed(() => (props.prevUrl ? Link : hasPrev.value ? 'button' : 'span'));
const nextTag = computed(() => (props.nextUrl ? Link : hasNext.value ? 'button' : 'span'));
</script>

<template>
    <nav aria-label="Pagination" class="flex items-center justify-between border-t border-line-2 pt-6 text-sm font-medium">
        <component
            :is="prevTag"
            :href="prevUrl || undefined"
            :type="prevTag === 'button' ? 'button' : undefined"
            aria-label="Previous page"
            :aria-disabled="prevUrl || hasPrev ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="prevUrl || hasPrev ? 'text-ink-2 transition-colors hover:text-accent' : 'cursor-default text-ink-3/40'"
            @click="!prevUrl && hasPrev && emit('navigate', currentPage - 1)"
        >
            <Icon :icon="ArrowLeft01Icon" class="size-4" />
            Previous
        </component>

        <span class="text-ink-3 tnum" aria-current="page">Page {{ currentPage }} of {{ lastPage }}</span>

        <component
            :is="nextTag"
            :href="nextUrl || undefined"
            :type="nextTag === 'button' ? 'button' : undefined"
            aria-label="Next page"
            :aria-disabled="nextUrl || hasNext ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="nextUrl || hasNext ? 'text-ink-2 transition-colors hover:text-accent' : 'cursor-default text-ink-3/40'"
            @click="!nextUrl && hasNext && emit('navigate', currentPage + 1)"
        >
            Next
            <Icon :icon="ArrowRight01Icon" class="size-4" />
        </component>
    </nav>
</template>
