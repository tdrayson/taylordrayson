<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import { Link } from '@inertiajs/vue3';
import { number } from '../../lib/format.js';

const props = defineProps({
    // [{ label, href, icon, active, count }], already ordered most-used first.
    chips: { type: Array, default: () => [] },
    // How many chips show inline before the rest collapse into the popover.
    limit: { type: Number, default: 12 },
});

// Popover open state and its search query.
const open = ref(false);
const search = ref('');
const root = ref(null);

// Inline chips: the most-used `limit`, plus the active one if it lives in the
// long tail, so the current filter is always visible without opening the popover.
const visible = computed(() => {
    const top = props.chips.slice(0, props.limit);
    const active = props.chips.find((chip) => chip.active);

    if (active && !top.includes(active)) {
        top.push(active);
    }

    return top;
});

// How many chips are hidden behind the "+N more" button.
const overflowCount = computed(() => Math.max(0, props.chips.length - props.limit));

// The popover lists every chip, filtered by the (case-insensitive) search, so
// any category is one search away regardless of how far down the tail it sits.
const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return query
        ? props.chips.filter((chip) => chip.label.toLowerCase().includes(query))
        : props.chips;
});

// Close the popover on an outside click or the Escape key.
function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onDocumentClick);
    document.addEventListener('keydown', onKeydown);
});

onBeforeUnmount(() => {
    document.removeEventListener('click', onDocumentClick);
    document.removeEventListener('keydown', onKeydown);
});
</script>

<template>
    <div v-if="chips.length" ref="root" class="relative mt-6">
        <div class="flex flex-wrap gap-2">
            <Link
                v-for="chip in visible"
                :key="chip.href"
                :href="chip.href"
                class="inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-caption font-medium transition-colors"
                :class="chip.active
                    ? 'bg-accent-500 text-neutral-0'
                    : 'bg-neutral-25 text-neutral-700 hover:bg-accent-50 hover:text-accent-700'"
            >
                <img v-if="chip.icon" :src="chip.icon" alt="" class="size-4 shrink-0 object-contain">
                {{ chip.label }}
            </Link>

            <button
                v-if="overflowCount > 0"
                type="button"
                class="inline-flex items-center rounded-full bg-neutral-25 px-3 py-1.5 text-caption font-medium text-neutral-700 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-expanded="open"
                @click="open = !open"
            >
                +{{ number(overflowCount) }} more
            </button>
        </div>

        <div
            v-if="open"
            class="absolute left-0 top-full z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-50 bg-neutral-0 p-2 shadow-card"
        >
            <input
                v-model="search"
                type="search"
                placeholder="Search categories…"
                aria-label="Search categories"
                class="mb-2 w-full rounded-lg border border-neutral-50 bg-neutral-25 px-3 py-1.5 text-caption text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
            <ul class="max-h-72 overflow-y-auto">
                <li v-for="chip in filtered" :key="chip.href">
                    <Link
                        :href="chip.href"
                        class="flex items-center justify-between gap-2 rounded-lg px-3 py-1.5 text-caption transition-colors hover:bg-accent-50 hover:text-accent-700"
                        :class="chip.active ? 'font-semibold text-accent-700' : 'text-neutral-700'"
                    >
                        <span class="inline-flex min-w-0 items-center gap-1.5">
                            <img v-if="chip.icon" :src="chip.icon" alt="" class="size-4 shrink-0 object-contain">
                            <span class="truncate">{{ chip.label }}</span>
                        </span>
                        <span v-if="chip.count != null" class="shrink-0 text-neutral-400 tnum">{{ number(chip.count) }}</span>
                    </Link>
                </li>
                <li v-if="!filtered.length" class="px-3 py-2 text-caption text-neutral-400">No matches</li>
            </ul>
        </div>
    </div>
</template>
