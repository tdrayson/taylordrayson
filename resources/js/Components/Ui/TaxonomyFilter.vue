<script setup>
import { computed, nextTick, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { Link, router } from '@inertiajs/vue3';
import { number } from '../../lib/format.js';

const props = defineProps({
    // [{ label, href, icon, active, count, all? }], categories ordered most-used
    // first with a leading { all: true } "All {type}" chip.
    chips: { type: Array, default: () => [] },
});

// The leading "All {type}" chip is always shown first; the rest are the
// categories, which are the ones that clamp to two rows / feed the popover.
const allChip = computed(() => props.chips.find((chip) => chip.all) ?? null);
const categoryChips = computed(() => props.chips.filter((chip) => !chip.all));

// The full inline sequence (All first, then categories) used for measuring and
// for slicing down to whatever fits in two rows.
const inlineChips = computed(() => (allChip.value ? [allChip.value, ...categoryChips.value] : categoryChips.value));

// How many inline chips fit in two rows; starts showing all until measured.
const inlineCount = ref(inlineChips.value.length);
// While measuring we render every chip (invisibly) so their wrap positions can
// be read, then clamp; keeps the collapse from flashing on screen.
const measuring = ref(true);
const rowRef = ref(null);

// Chips actually rendered inline: everything while measuring, else the clamped
// slice. The remainder lives behind the "more" button / in the popover.
const shownChips = computed(() => (measuring.value ? inlineChips.value : inlineChips.value.slice(0, inlineCount.value)));
const overflowCount = computed(() => Math.max(0, inlineChips.value.length - inlineCount.value));

// Measure how many chips sit within the first two rows, reserving one slot for
// the "more" button when the list actually overflows.
async function measure() {
    measuring.value = true;
    await nextTick();

    const container = rowRef.value;
    if (! container) {
        measuring.value = false;

        return;
    }

    const els = [...container.querySelectorAll('[data-chip]')];
    if (! els.length) {
        measuring.value = false;

        return;
    }

    const rowTops = [];
    let fit = 0;

    for (const el of els) {
        const top = el.offsetTop;

        if (! rowTops.length || top > rowTops[rowTops.length - 1] + 2) {
            rowTops.push(top);
        }

        if (rowTops.length > 2) {
            break;
        }

        fit++;
    }

    // Give the "+N more" button room on the second row when we overflow.
    inlineCount.value = fit < els.length ? Math.max(1, fit - 1) : fit;
    measuring.value = false;
}

let observer = null;
// Track the last measured width so the observer only re-measures on a real
// resize; our own clamp changes the row's height, which must not re-trigger it.
let lastWidth = 0;

onMounted(() => {
    measure();
    observer = new ResizeObserver((entries) => {
        const width = entries[0].contentRect.width;
        if (width !== lastWidth) {
            lastWidth = width;
            measure();
        }
    });
    if (rowRef.value) {
        observer.observe(rowRef.value);
    }
});

onBeforeUnmount(() => {
    observer?.disconnect();
    document.removeEventListener('click', onDocumentClick);
});

// Re-measure if the chip set changes (navigating between archives).
watch(() => props.chips, () => measure());

// --- Popover ---------------------------------------------------------------

const open = ref(false);
const search = ref('');
const searchInput = ref(null);
const listRef = ref(null);
// Index of the keyboard-highlighted result within `filtered`.
const activeIndex = ref(0);

// The popover lists every category, filtered by the case-insensitive query, so
// any category is one search away however far down the tail it sits.
const filtered = computed(() => {
    const query = search.value.trim().toLowerCase();

    return query
        ? categoryChips.value.filter((chip) => chip.label.toLowerCase().includes(query))
        : categoryChips.value;
});

// Reset the highlight to the top whenever the result set changes.
watch(filtered, () => {
    activeIndex.value = 0;
});

// Focus the search box and close on outside click when the popover opens.
watch(open, (isOpen) => {
    if (isOpen) {
        search.value = '';
        activeIndex.value = 0;
        nextTick(() => searchInput.value?.focus());
        document.addEventListener('click', onDocumentClick);
    } else {
        document.removeEventListener('click', onDocumentClick);
    }
});

function onDocumentClick(event) {
    if (rowRef.value && ! rowRef.value.parentElement.contains(event.target)) {
        open.value = false;
    }
}

// Arrow keys move the highlight, Enter navigates to it, Escape closes.
function onSearchKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        activeIndex.value = Math.min(activeIndex.value + 1, filtered.value.length - 1);
        scrollActiveIntoView();
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        activeIndex.value = Math.max(activeIndex.value - 1, 0);
        scrollActiveIntoView();
    } else if (event.key === 'Enter') {
        event.preventDefault();
        const chip = filtered.value[activeIndex.value];
        if (chip) {
            open.value = false;
            router.visit(chip.href);
        }
    } else if (event.key === 'Escape') {
        open.value = false;
    }
}

// Keep the highlighted option visible as the arrows move past the fold.
function scrollActiveIntoView() {
    nextTick(() => {
        listRef.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    });
}
</script>

<template>
    <div v-if="chips.length" class="relative mt-6">
        <div ref="rowRef" class="flex flex-wrap gap-2" :class="{ invisible: measuring }">
            <Link
                v-for="chip in shownChips"
                :key="chip.href"
                data-chip
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
                class="self-center text-caption font-medium text-neutral-500 underline-offset-2 transition-colors hover:text-accent-600 hover:underline focus-visible:text-accent-600 focus-visible:underline focus-visible:outline-none"
                :aria-expanded="open"
                @click.stop="open = ! open"
            >
                +{{ number(overflowCount) }} more
            </button>
        </div>

        <div
            v-if="open"
            class="absolute left-0 top-full z-20 mt-2 w-72 max-w-[calc(100vw-2rem)] rounded-xl border border-neutral-50 bg-neutral-0 p-2 shadow-card"
            @click.stop
        >
            <input
                ref="searchInput"
                v-model="search"
                type="search"
                role="combobox"
                aria-expanded="true"
                aria-controls="taxonomy-options"
                placeholder="Search categories…"
                aria-label="Search categories"
                class="mb-2 w-full rounded-lg border border-neutral-50 bg-neutral-25 px-3 py-1.5 text-caption text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @keydown="onSearchKeydown"
            >
            <ul id="taxonomy-options" ref="listRef" role="listbox" class="max-h-72 overflow-y-auto">
                <li v-for="(chip, index) in filtered" :key="chip.href" role="option" :aria-selected="index === activeIndex" :data-active="index === activeIndex">
                    <Link
                        :href="chip.href"
                        class="flex items-center justify-between gap-2 rounded-lg px-3 py-1.5 text-caption transition-colors"
                        :class="[
                            index === activeIndex ? 'bg-accent-50 text-accent-700' : 'text-neutral-700 hover:bg-accent-50 hover:text-accent-700',
                            chip.active ? 'font-semibold' : '',
                        ]"
                        @mouseenter="activeIndex = index"
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
