<script setup>
import { ref, watch } from 'vue';
import Input from '../Ui/Input.vue';

/**
 * A text field backed by a search: airports, airlines, books, places.
 *
 * The typed value is kept whatever happens, so a book Hardcover has never
 * heard of, or an airport with no row, is still enterable. Picking a result
 * may also fill sibling fields (a book's author, a place's coordinates), which
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

const query = ref(String(props.modelValue ?? ''));
const results = ref([]);
const open = ref(false);
const searching = ref(false);
let timer = null;

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
        open.value = true;
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

    open.value = false;
    results.value = [];
}
</script>

<template>
    <div class="relative">
        <Input
            :id="id"
            :model-value="query"
            :placeholder="placeholder"
            autocomplete="off"
            @update:model-value="onInput"
            @focus="query && search()"
            @blur="setTimeout(() => (open = false), 150)"
        />

        <ul
            v-if="open && results.length"
            class="absolute z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-lg"
        >
            <li v-for="result in results" :key="result.value + result.label">
                <button
                    type="button"
                    class="flex w-full items-baseline justify-between gap-3 px-3 py-1.5 text-left text-meta text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700"
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
