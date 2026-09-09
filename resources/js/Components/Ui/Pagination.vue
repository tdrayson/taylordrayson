<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from './Icon.vue';

const props = defineProps({
    currentPage: { type: Number, default: 1 },
    lastPage: { type: Number, default: 1 },
    prevUrl: { type: String, default: null },
    nextUrl: { type: String, default: null },
    // 'Newer'/'Older' on a time-ordered feed, where "previous" could mean
    // either direction.
    prevLabel: { type: String, default: 'Previous' },
    nextLabel: { type: String, default: 'Next' },
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
    <nav aria-label="Pagination" class="flex items-center justify-between border-t border-neutral-50 pt-6 text-sm font-medium">
        <component
            :is="prevTag"
            :href="prevUrl || undefined"
            :type="prevTag === 'button' ? 'button' : undefined"
            :aria-label="`${prevLabel} page`"
            :aria-disabled="prevUrl || hasPrev ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="prevUrl || hasPrev ? 'text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500' : 'text-neutral-500/40'"
            @click="!prevUrl && hasPrev && emit('navigate', currentPage - 1)"
        >
            <Icon name="ArrowLeft01Icon" class="size-4" />
            {{ prevLabel }}
        </component>

        <!-- A date-ordered feed says where it is by date; a flat list of one type
             says it by page number, which is what the fallback keeps. -->
        <span class="text-neutral-500 tnum" aria-current="page">
            <slot name="label">Page {{ currentPage }} of {{ lastPage }}</slot>
        </span>

        <component
            :is="nextTag"
            :href="nextUrl || undefined"
            :type="nextTag === 'button' ? 'button' : undefined"
            :aria-label="`${nextLabel} page`"
            :aria-disabled="nextUrl || hasNext ? undefined : 'true'"
            class="inline-flex items-center gap-1.5"
            :class="nextUrl || hasNext ? 'text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500' : 'text-neutral-500/40'"
            @click="!nextUrl && hasNext && emit('navigate', currentPage + 1)"
        >
            {{ nextLabel }}
            <Icon name="ArrowRight01Icon" class="size-4" />
        </component>
    </nav>
</template>
