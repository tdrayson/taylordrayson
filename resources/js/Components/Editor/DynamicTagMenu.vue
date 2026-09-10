<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import SuggestionMenu from './SuggestionMenu.vue';
import { useSuggestionPosition } from '../../lib/editor/suggestionPosition';

/**
 * The "{" dynamic tag menu. An empty query shows the cascading two-pane
 * browse used by the search field picker (categories left, that category's
 * tags right) so 36 tags never sit in one flat scrolling list. Once an author
 * types, it hands off to the ordinary flat, filtered `SuggestionMenu` (the
 * standard pattern for a suggestion trigger, matching `label` and `name`).
 */
const props = defineProps({
    categories: { type: Array, default: () => [] }, // [{ label, rows }]
    items: { type: Array, default: () => [] }, // flat filtered rows, once a query is typed
    query: { type: String, default: '' },
    active: { type: Number, default: 0 }, // active index within `items`
    activeCategory: { type: Number, default: 0 },
    activeField: { type: Number, default: 0 },
    rect: { type: Object, default: null },
    getRect: { type: Function, default: null },
    emptyLabel: { type: String, default: 'No matching tag' },
});

defineEmits(['pick', 'hover-category']);

const cascading = computed(() => ! props.query);

const activeRows = computed(() => props.categories[props.activeCategory]?.rows ?? []);

// Wide enough for both panes to show a full label and its live value without
// truncating either.
const size = computed(() => ({ width: 384, minHeight: 160 }));

const { style } = useSuggestionPosition(computed(() => props.rect), props.getRect, size);
</script>

<template>
    <SuggestionMenu
        v-if="! cascading"
        :items="items"
        :active="active"
        :rect="rect"
        :get-rect="getRect"
        :empty-label="emptyLabel"
        @pick="$emit('pick', $event)"
    />

    <div
        v-else
        class="fixed z-50 flex overflow-hidden rounded-lg border border-neutral-100 bg-neutral-0 shadow-lg"
        :style="style"
    >
        <ul class="w-1/2 overflow-y-auto border-r border-neutral-50 py-1" aria-label="Tag categories">
            <li v-for="(category, index) in categories" :key="category.label">
                <button
                    type="button"
                    :aria-current="index === activeCategory"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-meta transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeCategory ? 'bg-neutral-25 text-accent-500' : 'text-neutral-700 hover:bg-neutral-25'"
                    @mouseenter="$emit('hover-category', index)"
                    @click="$emit('hover-category', index)"
                >
                    <span class="min-w-0 truncate">{{ category.label }}</span>
                    <Icon name="ArrowRight01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                </button>
            </li>
        </ul>

        <ul
            class="w-1/2 overflow-y-auto py-1"
            role="listbox"
            :aria-label="categories[activeCategory] ? `${categories[activeCategory].label} tags` : 'Tags'"
        >
            <li v-if="! activeRows.length" class="px-3 py-2 text-meta text-neutral-500">{{ emptyLabel }}</li>

            <li v-for="(row, index) in activeRows" :key="row.id">
                <button
                    type="button"
                    role="option"
                    :aria-selected="index === activeField"
                    class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeField ? 'bg-neutral-25' : 'hover:bg-neutral-25'"
                    @mousedown.prevent="$emit('pick', row)"
                >
                    <span class="text-meta" :class="index === activeField ? 'text-accent-500' : 'text-neutral-900'">{{ row.label }}</span>
                    <span v-if="row.detail" class="text-label text-neutral-500">{{ row.detail }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
