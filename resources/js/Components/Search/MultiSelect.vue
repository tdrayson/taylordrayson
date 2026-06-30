<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ArrowDown01Icon, Search01Icon, Tick02Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    options: { type: Array, default: () => [] },
    placeholder: { type: String, default: 'Select…' },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);
const query = ref('');

const filtered = computed(() => {
    const term = query.value.trim().toLowerCase();

    return term ? props.options.filter((option) => String(option).toLowerCase().includes(term)) : props.options;
});

const summary = computed(() => (props.modelValue.length ? props.modelValue.join(', ') : null));
const isSelected = (option) => props.modelValue.includes(option);

function toggleOption(option) {
    const next = isSelected(option) ? props.modelValue.filter((value) => value !== option) : [...props.modelValue, option];
    emit('update:modelValue', next);
}

function toggle() {
    open.value = !open.value;

    if (open.value) {
        query.value = '';
    }
}

function onDocumentClick(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('click', onDocumentClick));
onUnmounted(() => document.removeEventListener('click', onDocumentClick));
</script>

<template>
    <div ref="root" class="relative" @keydown.esc="open = false">
        <button
            type="button"
            aria-haspopup="true"
            :aria-expanded="open"
            class="flex w-full items-center gap-2 rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2.5 text-left text-meta transition-colors hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="summary ? 'text-neutral-900' : 'text-neutral-500'"
            @click="toggle"
        >
            <span class="flex-1 truncate">{{ summary ?? placeholder }}</span>
            <span v-if="modelValue.length" class="shrink-0 rounded-full bg-accent-50 px-1.5 text-label text-accent-700 tnum">{{ modelValue.length }}</span>
            <Icon :icon="ArrowDown01Icon" class="size-3.5 shrink-0 text-neutral-500" />
        </button>

        <div v-if="open" class="absolute left-0 z-50 mt-2 w-full min-w-56 rounded-lg border border-neutral-50 bg-neutral-0 shadow-card">
            <div class="flex items-center gap-2 border-b border-neutral-50 px-3">
                <Icon :icon="Search01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search…"
                    aria-label="Search options"
                    class="w-full bg-transparent py-2.5 text-meta text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                >
            </div>
            <ul class="max-h-56 overflow-y-auto py-1">
                <li v-for="option in filtered" :key="option">
                    <button
                        type="button"
                        :aria-pressed="isSelected(option)"
                        class="flex w-full items-center gap-2.5 px-3 py-2 text-left text-meta transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none"
                        :class="isSelected(option) ? 'text-neutral-900' : 'text-neutral-700'"
                        @click="toggleOption(option)"
                    >
                        <span
                            class="flex size-4 shrink-0 items-center justify-center rounded border transition-colors"
                            :class="isSelected(option) ? 'border-accent-500 bg-accent-500 text-neutral-0' : 'border-neutral-100'"
                        >
                            <Icon v-if="isSelected(option)" :icon="Tick02Icon" class="size-3" :stroke-width="2.5" />
                        </span>
                        <span class="flex-1 truncate">{{ option }}</span>
                    </button>
                </li>
                <li v-if="!filtered.length" class="px-3 py-3 text-center text-caption text-neutral-500">No matches</li>
            </ul>
        </div>
    </div>
</template>
