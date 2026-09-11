<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import SuggestionMenu from './SuggestionMenu.vue';
import { useSuggestionPosition } from '../../lib/editor/suggestionPosition';

/**
 * The "{" dynamic tag menu. An empty query shows the cascading browse used by
 * the search field picker (categories left, that category's tags next) so 36
 * tags never sit in one flat scrolling list. A category whose tags split into
 * sub-groups (Rings' Move/Exercise/Stand) grows a third pane: its rows are
 * parent rows, not selectable, and the pane to their right holds their own
 * leaves. Once an author types, it hands off to the ordinary flat, filtered
 * `SuggestionMenu` (the standard pattern for a suggestion trigger, matching
 * `label` and `name`).
 */
const props = defineProps({
    categories: { type: Array, default: () => [] }, // [{ label, rows }]
    items: { type: Array, default: () => [] }, // flat filtered rows, once a query is typed
    query: { type: String, default: '' },
    active: { type: Number, default: 0 }, // active index within `items`
    activeCategory: { type: Number, default: 0 },
    activeField: { type: Number, default: 0 },
    activeChild: { type: Number, default: 0 }, // active index within the active field's third pane, if any
    rect: { type: Object, default: null },
    getRect: { type: Function, default: null },
    emptyLabel: { type: String, default: 'No matching tag' },
});

defineEmits(['pick', 'hover-category', 'hover-field', 'hover-child']);

const cascading = computed(() => ! props.query);

const activeRows = computed(() => props.categories[props.activeCategory]?.rows ?? []);
const activeRow = computed(() => activeRows.value[props.activeField] ?? null);
const thirdPaneRows = computed(() => (activeRow.value?.isParent ? activeRow.value.children : []));

// Both panes use `mousemove`, not a CSS `:hover`, to paint the active row: it
// is what `CommandPalette` and `useListboxNavigation` already do, and it
// keeps the mouse and the arrow keys sharing one index instead of each
// painting a row of its own.

// Each pane is a fixed 12rem so a third pane can be added or dropped without
// resizing the other two; wide enough for a full label and its live value
// without truncating either.
const size = computed(() => ({ width: thirdPaneRows.value.length ? 576 : 384, minHeight: 160 }));

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
        class="fixed z-50 flex items-start overflow-hidden rounded-lg border border-neutral-100 bg-neutral-0 shadow-lg"
        :style="style"
    >
        <ul class="w-48 shrink-0 overflow-y-auto border-r border-neutral-50 py-1" aria-label="Tag categories">
            <li v-for="(category, index) in categories" :key="category.label">
                <button
                    type="button"
                    :aria-current="index === activeCategory"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-meta transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeCategory ? 'bg-neutral-25 text-accent-500' : 'text-neutral-700'"
                    @mousemove="$emit('hover-category', index)"
                    @click="$emit('hover-category', index)"
                >
                    <span class="min-w-0 truncate">{{ category.label }}</span>
                    <Icon name="ArrowRight01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                </button>
            </li>
        </ul>

        <ul
            class="max-h-72 w-48 shrink-0 snap-y snap-mandatory overflow-y-auto border-r border-neutral-50 py-1"
            role="listbox"
            :aria-label="categories[activeCategory] ? `${categories[activeCategory].label} tags` : 'Tags'"
        >
            <li v-if="! activeRows.length" class="snap-start px-3 py-2 text-meta text-neutral-500">{{ emptyLabel }}</li>

            <li v-for="(row, index) in activeRows" :key="row.id" class="snap-start">
                <!-- A parent row (e.g. Rings' Move) only opens its own third
                     pane; it carries no value of its own to pick. -->
                <button
                    v-if="row.isParent"
                    type="button"
                    :aria-current="index === activeField"
                    class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-meta transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeField ? 'bg-neutral-25 text-accent-500' : 'text-neutral-700'"
                    @mousemove="$emit('hover-field', index)"
                >
                    <span class="min-w-0 truncate">{{ row.label }}</span>
                    <Icon name="ArrowRight01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                </button>

                <button
                    v-else
                    type="button"
                    role="option"
                    :aria-selected="index === activeField"
                    :aria-label="row.ariaLabel"
                    class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeField ? 'bg-neutral-25' : ''"
                    @mousemove="$emit('hover-field', index)"
                    @mousedown.prevent="$emit('pick', row)"
                >
                    <span class="text-meta" :class="index === activeField ? 'text-accent-500' : 'text-neutral-900'">{{ row.label }}</span>
                    <span v-if="row.detail" class="text-label" :class="row.detailResolved ? 'text-neutral-500' : 'text-neutral-400'">{{ row.detail }}</span>
                </button>
            </li>
        </ul>

        <ul
            v-if="thirdPaneRows.length"
            class="max-h-72 w-48 shrink-0 snap-y snap-mandatory overflow-y-auto py-1"
            role="listbox"
            :aria-label="`${activeRow.label} tags`"
        >
            <li v-for="(row, index) in thirdPaneRows" :key="row.id" class="snap-start">
                <button
                    type="button"
                    role="option"
                    :aria-selected="index === activeChild"
                    :aria-label="row.ariaLabel"
                    class="flex w-full flex-col items-start gap-0.5 px-3 py-2 text-left transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                    :class="index === activeChild ? 'bg-neutral-25' : ''"
                    @mousemove="$emit('hover-child', index)"
                    @mousedown.prevent="$emit('pick', row)"
                >
                    <span class="text-meta" :class="index === activeChild ? 'text-accent-500' : 'text-neutral-900'">{{ row.label }}</span>
                    <span v-if="row.detail" class="text-label" :class="row.detailResolved ? 'text-neutral-500' : 'text-neutral-400'">{{ row.detail }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
