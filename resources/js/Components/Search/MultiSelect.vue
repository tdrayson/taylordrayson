<script setup>
import { ref, computed, nextTick, watch } from 'vue';
import Icon from '../Ui/Icon.vue';
import { useDismissable } from '../../composables/useDismissable.js';
import { useListboxNavigation } from '../../composables/useListboxNavigation.js';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] }, // [{ value, label }]
    placeholder: { type: String, default: 'Select…' },
});

const emit = defineEmits(['update:modelValue']);

const { isOpen: open, root, close, toggle } = useDismissable();
const query = ref('');
const queryInput = ref(null);
const listEl = ref(null);

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return term ? props.options.filter((option) => option.label.toLowerCase().includes(term)) : props.options;
});

// The trigger reads back what was picked by label, so it matches the list it
// came from rather than showing the stored values.
const summary = computed(() => {
    const chosen = props.options.filter((option) => props.modelValue.includes(option.value));

    return chosen.length ? chosen.map((option) => option.label).join(', ') : null;
});

const isSelected = (option) => props.modelValue.includes(option.value);

function toggleOption(option) {
    const next = isSelected(option)
        ? props.modelValue.filter((value) => value !== option.value)
        : [...props.modelValue, option.value];

    emit('update:modelValue', next);
}

// Enter toggles the highlighted option rather than closing, since the whole
// point of a multi-select is picking more than one without reaching for the
// mouse between each.
const { activeIndex, onKeydown } = useListboxNavigation(filtered, {
    listEl,
    onSelect: (option) => option !== undefined && toggleOption(option),
});

// Opening clears the search and focuses it, so @keydown (bound on the input)
// has something focused to bubble from as soon as the popover appears.
watch(open, (isOpen) => {
    if (isOpen) {
        query.value = '';
        nextTick(() => queryInput.value?.focus());

        return;
    }

    // Closing must not strand focus on a removed input; return it to the
    // trigger, identified by the aria-expanded every trigger carries.
    nextTick(() => {
        root.value?.querySelector('[aria-expanded]')?.focus();
    });
});
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            aria-haspopup="true"
            :aria-expanded="open"
            class="flex w-full items-center gap-2 min-h-11 rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2.5 text-left text-sm transition-colors hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="summary ? 'text-neutral-900' : 'text-neutral-500'"
            @click="toggle"
        >
            <span class="flex-1 truncate">{{ summary ?? placeholder }}</span>
            <span v-if="modelValue.length" class="shrink-0 rounded-full bg-accent-50 px-1.5 text-2xs font-semibold text-accent-700 tabular-nums">{{ modelValue.length }}</span>
            <Icon name="ArrowDown01Icon" class="size-3.5 shrink-0 text-neutral-500" />
        </button>

        <div v-if="open" class="absolute left-0 z-50 mt-2 w-full min-w-56 rounded-lg border border-neutral-50 bg-neutral-0 shadow-card">
            <div class="flex items-center gap-2 border-b border-neutral-50 px-3">
                <Icon name="Search01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                <input
                    ref="queryInput"
                    v-model="query"
                    type="text"
                    placeholder="Search…"
                    aria-label="Search options"
                    class="w-full bg-transparent py-2.5 text-sm text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                    @keydown="onKeydown"
                >
            </div>
            <ul ref="listEl" role="listbox" aria-multiselectable="true" class="max-h-56 overflow-y-auto py-1">
                <li v-for="(option, index) in filtered" :key="option.value">
                    <button
                        type="button"
                        role="option"
                        :aria-selected="isSelected(option)"
                        :data-active="index === activeIndex"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-sm transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none"
                        :class="[
                            isSelected(option) ? 'text-neutral-900' : 'text-neutral-700',
                            index === activeIndex ? 'bg-neutral-25' : '',
                        ]"
                        @click="toggleOption(option)"
                    >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border transition-colors"
                            :class="isSelected(option) ? 'border-accent-500 bg-accent-500 text-neutral-0' : 'border-neutral-100'"
                        >
                            <Icon v-if="isSelected(option)" name="Tick02Icon" class="size-3" :stroke-width="2.5" />
                        </span>
                        <span class="flex-1 truncate">{{ option.label }}</span>
                    </button>
                </li>
                <li v-if="!filtered.length" class="px-3 py-3 text-center text-xs text-neutral-500">No matches</li>
            </ul>
        </div>
    </div>
</template>
