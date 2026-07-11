<script setup>
import { ref, computed, watch, nextTick, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import * as chrono from 'chrono-node';
import fuzzysort from 'fuzzysort';
import { Search01Icon, Calendar03Icon, SparklesIcon, Tag01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import { useCommandPalette } from '../../composables/useCommandPalette';
import { pageCommands, archiveCommands } from '../../navigation.js';
import { entryType } from '../../entryTypes.js';

const { isOpen, close, toggle } = useCommandPalette();

const query = ref('');
const activeIndex = ref(0);
const input = ref(null);
const listEl = ref(null);

// Async matches from the server (debounced fetch): timeline entries, plus
// taxonomy destination pages drawn live from the registry.
const entryResults = ref([]);
const destinationResults = ref([]);
// True from the keystroke until the debounced server fetch resolves, so the
// "No matches" empty state never flashes in the gap before async results land.
const searching = ref(false);
let searchTimer = null;
let searchController = null;

const now = new Date();
const pad = (value) => String(value).padStart(2, '0');
const jumpCommands = [
    { label: 'Today', href: `/${now.getFullYear()}/${pad(now.getMonth() + 1)}/${pad(now.getDate())}`, icon: Calendar03Icon, keywords: 'this day' },
    { label: 'This month', href: `/${now.getFullYear()}/${pad(now.getMonth() + 1)}`, icon: Calendar03Icon },
    { label: 'This year', href: `/${now.getFullYear()}`, icon: Calendar03Icon },
    { label: 'On this day', href: '/on-this-day', icon: Calendar03Icon, keywords: 'history past years' },
    { label: "I'm feeling lucky", href: '/lucky', icon: SparklesIcon, keywords: 'random surprise' },
];

const baseSections = [
    { heading: 'Pages', items: pageCommands },
    { heading: 'Archives', items: archiveCommands },
    { heading: 'Jump to', items: jumpCommands },
];

// Category weight breaks ties so primary pages outrank archives, which outrank
// jump shortcuts, when match quality is otherwise equal.
const sectionWeight = { Pages: 3, Archives: 2, 'Jump to': 1 };

const allItems = baseSections.flatMap((section) =>
    section.items.map((item) => ({ ...item, weight: sectionWeight[section.heading] ?? 0 })),
);

const dayLabel = (date) => date.toLocaleDateString('en-GB', { weekday: 'short', day: 'numeric', month: 'long', year: 'numeric' });
const monthLabel = (date) => date.toLocaleDateString('en-GB', { month: 'long', year: 'numeric' });

// Natural-language date parsing (chrono): resolve a query to a Day / Month / Year
// page jump, at the granularity chrono is confident about.
function parseDates(text) {
    const yearOnly = text.match(/^(\d{4})$/);

    if (yearOnly) {
        const year = Number(yearOnly[1]);

        return year >= 1990 && year <= 2100
            ? [{ label: String(year), href: `/${year}`, icon: Calendar03Icon }]
            : [];
    }

    if (text.length < 2) {
        return [];
    }

    const results = chrono.parse(text, new Date(), { forwardDate: false });

    // A range (e.g. "last week to today") has no single page to jump to.
    if (!results.length || results[0].end) {
        return [];
    }

    const start = results[0].start;
    const date = start.date();
    const year = date.getFullYear();
    const month = pad(date.getMonth() + 1);
    const day = pad(date.getDate());

    // Offer only the granularity the query actually expressed.
    if (start.isCertain('day') || start.isCertain('weekday')) {
        return [{ label: dayLabel(date), href: `/${year}/${month}/${day}`, icon: Calendar03Icon }];
    }

    if (start.isCertain('month')) {
        return [{ label: monthLabel(date), href: `/${year}/${month}`, icon: Calendar03Icon }];
    }

    return [{ label: String(year), href: `/${year}`, icon: Calendar03Icon }];
}

// fuzzysort ranks the static destinations (pages + archive indexes) over both
// the label and a discounted keyword field, so exact and prefix hits float to
// the top while synonyms still match. Category weight breaks near-ties so a
// primary page edges out an archive of comparable score.
function rankItems(text) {
    return fuzzysort
        .go(text, allItems, {
            keys: ['label', 'keywords'],
            scoreFn: (keysResult) => Math.max(
                keysResult[0] ? keysResult[0].score : 0,
                keysResult[1] ? keysResult[1].score * 0.5 : 0,
            ),
            limit: 30,
        })
        .map((result) => ({ item: result.obj, score: result.score }))
        .sort((a, b) =>
            b.score - a.score
            || b.item.weight - a.item.weight
            || a.item.label.length - b.item.label.length,
        )
        .map((entry) => entry.item);
}

const sections = computed(() => {
    const trimmed = query.value.trim();

    if (!trimmed) {
        return withIndices(baseSections);
    }

    const raw = [];

    const dates = parseDates(trimmed);

    if (dates.length) {
        raw.push({ heading: 'Date', items: dates });
    }

    const ranked = rankItems(trimmed);

    if (ranked.length) {
        raw.push({ heading: 'Results', items: ranked });
    }

    if (destinationResults.value.length) {
        raw.push({
            heading: 'Jump to',
            items: destinationResults.value.map((destination) => ({
                label: destination.label,
                meta: destination.section,
                href: destination.url,
                // Tags are cross-type, so they get a tag icon; other taxonomy jumps
                // keep their owning type's icon (a flight for an airline, etc.).
                icon: destination.tag ? Tag01Icon : entryType(destination.type).icon,
            })),
        });
    }

    if (entryResults.value.length) {
        raw.push({
            heading: 'Entries',
            items: entryResults.value.map((entry) => ({
                label: entry.title,
                meta: entry.date,
                href: entry.url,
                icon: entryType(entry.type).icon,
            })),
        });
    }

    return withIndices(raw);
});

// Stamp each item with a flat index so arrow-key navigation can run across sections.
function withIndices(raw) {
    let index = 0;

    return raw.map((section) => ({
        heading: section.heading,
        items: section.items.map((item) => ({ ...item, index: index++ })),
    }));
}

const flatItems = computed(() => sections.value.flatMap((section) => section.items));

watch(query, (value) => {
    activeIndex.value = 0;

    const term = value.trim();

    if (searchTimer) {
        clearTimeout(searchTimer);
    }

    if (searchController) {
        searchController.abort();
        searchController = null;
    }

    if (term.length < 2) {
        searching.value = false;
        entryResults.value = [];
        destinationResults.value = [];

        return;
    }

    searching.value = true;

    searchTimer = setTimeout(async () => {
        searchController = new AbortController();

        try {
            const response = await fetch(`/search/suggest?q=${encodeURIComponent(term)}`, {
                headers: { Accept: 'application/json' },
                signal: searchController.signal,
            });

            const payload = response.ok ? await response.json() : {};
            entryResults.value = payload.results ?? [];
            destinationResults.value = payload.destinations ?? [];
            searching.value = false;
        } catch (error) {
            if (error.name !== 'AbortError') {
                entryResults.value = [];
                destinationResults.value = [];
                searching.value = false;
            }
        }
    }, 180);
});

watch(isOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';

    if (open) {
        query.value = '';
        activeIndex.value = 0;
        nextTick(() => input.value?.focus());
    } else {
        if (searchTimer) {
            clearTimeout(searchTimer);
        }

        searchController?.abort();
        searchController = null;
        searching.value = false;
        entryResults.value = [];
        destinationResults.value = [];
    }
});

function move(delta) {
    const count = flatItems.value.length;

    if (count === 0) {
        return;
    }

    activeIndex.value = (activeIndex.value + delta + count) % count;

    nextTick(() => {
        listEl.value?.querySelector('[data-active="true"]')?.scrollIntoView({ block: 'nearest' });
    });
}

function select(item) {
    if (!item) {
        return;
    }

    close();
    router.visit(item.href);
}

function onInputKeydown(event) {
    if (event.key === 'ArrowDown') {
        event.preventDefault();
        move(1);
    } else if (event.key === 'ArrowUp') {
        event.preventDefault();
        move(-1);
    } else if (event.key === 'Enter') {
        event.preventDefault();
        select(flatItems.value[activeIndex.value]);
    }
}

// Keep keys inside the palette: stop them reaching window-level listeners (e.g.
// the 404 Snake game), trap Tab to the input, and own Escape / ⌘K while open.
function onPanelKeydown(event) {
    event.stopPropagation();

    if (event.key === 'Escape') {
        event.preventDefault();
        close();
    } else if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        close();
    } else if (event.key === 'Tab') {
        event.preventDefault();
        input.value?.focus();
    }
}

function onGlobalKeydown(event) {
    if ((event.metaKey || event.ctrlKey) && event.key.toLowerCase() === 'k') {
        event.preventDefault();
        toggle();
    } else if (event.key === 'Escape' && isOpen.value) {
        close();
    }
}

onMounted(() => document.addEventListener('keydown', onGlobalKeydown));
onUnmounted(() => {
    document.removeEventListener('keydown', onGlobalKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <Teleport to="body">
        <Transition name="palette">
            <div
                v-if="isOpen"
                class="overlay fixed inset-0 flex items-start justify-center px-4"
                @click.self="close"
            >
                <!-- Fixed bg-black (not bg-neutral-900): the palette backdrop is an
                     intentional dim scrim in both themes, so it must not invert. -->
                <div class="backdrop absolute inset-0 bg-black/45" @click="close" />

                <div
                    class="panel relative flex w-full max-w-xl flex-col overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0 shadow-card"
                    role="dialog"
                    aria-modal="true"
                    aria-label="Search and navigate"
                    @keydown="onPanelKeydown"
                >
                    <div class="flex items-center gap-3 border-b border-neutral-50 px-4">
                        <Icon :icon="Search01Icon" class="size-4 shrink-0 text-neutral-500" />
                        <input
                            ref="input"
                            v-model="query"
                            type="text"
                            placeholder="Search pages, archives, dates…"
                            class="w-full bg-transparent py-4 text-nav text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                            autocomplete="off"
                            spellcheck="false"
                            @keydown="onInputKeydown"
                        >
                    </div>

                    <div ref="listEl" class="max-h-80 overflow-y-auto py-2">
                        <template v-for="section in sections" :key="section.heading">
                            <div class="px-4 pb-1 pt-2 text-label uppercase text-neutral-500">{{ section.heading }}</div>
                            <button
                                v-for="item in section.items"
                                :key="item.href"
                                type="button"
                                :data-active="item.index === activeIndex"
                                class="flex w-full items-center gap-3 px-4 py-2.5 text-left text-nav transition-colors"
                                :class="item.index === activeIndex ? 'bg-neutral-25 text-neutral-900' : 'text-neutral-700'"
                                @click="select(item)"
                                @mousemove="activeIndex = item.index"
                            >
                                <Icon :icon="item.icon" class="size-4 shrink-0 text-neutral-500" />
                                <span class="flex-1 truncate">{{ item.label }}</span>
                                <span v-if="item.meta" class="shrink-0 text-label text-neutral-500">{{ item.meta }}</span>
                                <span class="w-3 shrink-0 text-right text-label text-neutral-500">{{ item.index === activeIndex ? '↵' : '' }}</span>
                            </button>
                        </template>

                        <p v-if="flatItems.length === 0 && searching" class="px-4 py-6 text-center text-meta text-neutral-500">
                            Searching…
                        </p>
                        <p v-else-if="flatItems.length === 0" class="px-4 py-6 text-center text-meta text-neutral-500">
                            No matches for &ldquo;{{ query }}&rdquo;
                        </p>
                    </div>

                    <div class="flex items-center gap-4 border-t border-neutral-50 px-4 py-2.5 text-label text-neutral-500">
                        <span><kbd>↑</kbd><kbd>↓</kbd> navigate</span>
                        <span><kbd>↵</kbd> open</span>
                        <span><kbd>esc</kbd> close</span>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<style scoped>
.overlay {
    z-index: 100;
    padding-top: 12vh;
}

kbd {
    display: inline-block;
    min-width: 1.1rem;
    padding: 0 0.25rem;
    margin-right: 0.15rem;
    text-align: center;
    border: 1px solid var(--color-neutral-50);
    border-radius: 4px;
    background: var(--color-neutral-25);
}

/* Root anchors Vue's transition timing (and fades opacity). */
.palette-enter-active,
.palette-leave-active {
    transition: opacity 0.28s ease;
}

.palette-enter-from,
.palette-leave-to {
    opacity: 0;
}

.palette-enter-active .panel,
.palette-leave-active .panel {
    transition: transform 0.28s ease;
}

.palette-enter-from .panel,
.palette-leave-to .panel {
    transform: translateY(-8px);
}

@media (prefers-reduced-motion: reduce) {
    .palette-enter-active,
    .palette-leave-active,
    .palette-enter-active .panel,
    .palette-leave-active .panel {
        transition: none;
    }
}
</style>
