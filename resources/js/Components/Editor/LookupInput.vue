<script setup>
import { ref, watch } from 'vue';
import Input from '../Ui/Input.vue';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { useListNavigation } from '../../lib/editor/listNavigation.js';

/**
 * A text field backed by a search. The typed value is always kept, so an airport
 * with no row is still enterable. Picking a result may fill sibling fields, which
 * is emitted separately rather than folded into the value.
 */
const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    // Which /lookup source backs this field.
    source: { type: String, required: true },
    placeholder: { type: String, default: '' },
    id: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue', 'fill']);

const { isOpen: open, root, open: show, close } = useDismissable();
const query = ref(String(props.modelValue ?? ''));
const results = ref([]);
const searching = ref(false);
let timer = null;

const { active, onKeydown } = useListNavigation(results, {
    onSelect: (result) => pick(result),
    onDismiss: () => close(),
});

watch(() => props.modelValue, (value) => {
    // Only follow the prop when the field is not being typed in, or every
    // keystroke would fight the value coming back down.
    if (! open.value) {
        query.value = String(value ?? '');
    }
});

async function search() {
    searching.value = true;

    try {
        const response = await fetch(`/lookup/${props.source}?q=${encodeURIComponent(query.value)}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        results.value = response.ok ? (await response.json()).data ?? [] : [];
        show();
    } catch {
        // A failed lookup leaves what was typed alone rather than clearing it.
        results.value = [];
    } finally {
        searching.value = false;
    }
}

function onInput(value) {
    query.value = value;
    emit('update:modelValue', value);

    clearTimeout(timer);
    timer = setTimeout(search, 250);
}

function pick(result) {
    query.value = result.value;
    emit('update:modelValue', result.value);

    if (result.fill) {
        emit('fill', result.fill);
    }

    close();
    results.value = [];
}
</script>

<template>
    <div ref="root" class="relative">
        <Input
            :id="id"
            :model-value="query"
            :placeholder="placeholder"
            autocomplete="off"
            role="combobox"
            :aria-expanded="open"
            aria-autocomplete="list"
            @update:model-value="onInput"
            @focus="query && search()"
            @keydown="onKeydown"
        />

        <ul
            v-if="open && results.length"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
            role="listbox"
        >
            <li v-for="(result, index) in results" :key="result.value + result.label">
                <button
                    type="button"
                    role="option"
                    :aria-selected="index === active"
                    class="flex w-full items-baseline justify-between gap-3 px-3 py-1.5 text-left text-meta transition-colors"
                    :class="index === active ? 'bg-accent-50 text-accent-700' : 'text-neutral-900 hover:bg-accent-50 hover:text-accent-700'"
                    @mousedown.prevent="pick(result)"
                >
                    <span class="min-w-0 truncate">{{ result.label }}</span>
                    <span v-if="result.detail" class="shrink-0 text-caption text-neutral-500">{{ result.detail }}</span>
                </button>
            </li>
        </ul>

        <p v-if="searching" class="mt-1 text-caption text-neutral-500">Searching...</p>
    </div>
</template>
