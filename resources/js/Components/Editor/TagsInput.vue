<script setup>
import { computed, ref } from 'vue';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { useListNavigation } from '../../lib/editor/listNavigation.js';

/**
 * Tags as chips, autocompleting over existing tags ranked by use, so the same
 * idea does not end up split across "Theatre", "theatre" and "theater". Comma or
 * Enter commits; backspace on an empty field removes the last chip.
 */
const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const { isOpen: open, root, open: show, close } = useDismissable();
const query = ref('');
const suggestions = ref([]);
let timer = null;

const tags = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : []));

// Already-chosen tags are dropped from the list: offering one you have is a
// row that does nothing.
const offered = computed(() => suggestions.value.filter(
    (suggestion) => ! tags.value.some((tag) => tag.toLowerCase() === suggestion.value.toLowerCase()),
));

async function search() {
    try {
        const response = await fetch(`/lookup/tag?q=${encodeURIComponent(query.value)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        suggestions.value = response.ok ? (await response.json()).data ?? [] : [];
        show();
    } catch {
        suggestions.value = [];
    }
}

function onInput(value) {
    // A comma is a commit, not a character: typing "gig," makes the chip.
    if (value.includes(',')) {
        value.split(',').forEach((part) => commit(part));
        query.value = '';

        return;
    }

    query.value = value;
    clearTimeout(timer);
    timer = setTimeout(search, 200);
}

/** Add a tag, ignoring blanks and case-insensitive duplicates. */
function commit(value) {
    const name = value.trim();

    if (name === '' || tags.value.some((tag) => tag.toLowerCase() === name.toLowerCase())) {
        return;
    }

    emit('update:modelValue', [...tags.value, name]);
}

function pick(suggestion) {
    commit(suggestion.value);
    query.value = '';
    close();
}

function remove(name) {
    emit('update:modelValue', tags.value.filter((tag) => tag !== name));
}

const { active, onKeydown: onListKeydown } = useListNavigation(offered, {
    onSelect: (suggestion) => pick(suggestion),
    onDismiss: () => close(),
});

/**
 * Enter takes the highlighted suggestion when the list is open, and otherwise
 * commits whatever was typed: a tag that does not exist yet still has to be
 * creatable, which is why this is not a plain select.
 */
function onKeydown(event) {
    if (event.key === 'Enter' && (! open.value || ! offered.value.length)) {
        event.preventDefault();
        commit(query.value);
        query.value = '';

        return;
    }

    onListKeydown(event);
}

function onBackspace() {
    if (query.value === '' && tags.value.length) {
        remove(tags.value[tags.value.length - 1]);
    }
}
</script>

<template>
    <div ref="root" class="relative">
        <div class="flex flex-wrap items-center gap-1.5 rounded-md border border-neutral-100 bg-neutral-0 px-2 py-1.5 focus-within:border-accent-500">
            <span
                v-for="tag in tags"
                :key="tag"
                class="inline-flex items-center gap-1 rounded bg-accent-50 py-0.5 pl-2 pr-1 text-meta text-accent-700"
            >
                {{ tag }}
                <button
                    type="button"
                    class="rounded px-1 leading-none text-accent-700/70 transition-colors hover:text-accent-700"
                    :aria-label="`Remove ${tag}`"
                    @click="remove(tag)"
                >×</button>
            </span>

            <input
                :id="id"
                :value="query"
                type="text"
                :placeholder="tags.length ? '' : 'Add a tag'"
                class="min-w-24 flex-1 border-none bg-transparent p-0 text-meta text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                autocomplete="off"
                @input="onInput($event.target.value)"
                @focus="search"
                role="combobox"
                :aria-expanded="open"
                aria-autocomplete="list"
                @keydown="onKeydown"
                @keydown.backspace="onBackspace"
            >
        </div>

        <ul
            v-if="open && offered.length"
            class="absolute z-30 mt-1 max-h-56 w-full overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
            role="listbox"
        >
            <li v-for="(suggestion, index) in offered" :key="suggestion.value">
                <button
                    type="button"
                    role="option"
                    :aria-selected="index === active"
                    class="flex w-full items-baseline justify-between gap-3 px-3 py-1.5 text-left text-meta transition-colors"
                    :class="index === active ? 'bg-accent-50 text-accent-700' : 'text-neutral-900 hover:bg-accent-50 hover:text-accent-700'"
                    @mousedown.prevent="pick(suggestion)"
                >
                    <span class="min-w-0 truncate">{{ suggestion.label }}</span>
                    <span v-if="suggestion.detail" class="shrink-0 text-caption text-neutral-500">{{ suggestion.detail }}</span>
                </button>
            </li>
        </ul>
    </div>
</template>
