<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ArrowDown01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    fields: { type: Array, required: true },
    modelValue: { type: String, required: true },
});

const emit = defineEmits(['update:modelValue']);

const open = ref(false);
const root = ref(null);

// Group fields into [{ label, fields }] in first-seen order for the cascading menu.
const categories = computed(() => {
    const order = [];
    const byLabel = {};

    for (const field of props.fields) {
        if (!byLabel[field.category]) {
            byLabel[field.category] = { label: field.category, fields: [] };
            order.push(byLabel[field.category]);
        }

        byLabel[field.category].fields.push(field);
    }

    return order;
});

const current = computed(() => props.fields.find((field) => field.key === props.modelValue) ?? props.fields[0]);
const activeCategory = ref(current.value?.category ?? categories.value[0]?.label);

const activeFields = computed(
    () => categories.value.find((category) => category.label === activeCategory.value)?.fields ?? []
);

function toggle() {
    open.value = !open.value;

    if (open.value) {
        activeCategory.value = current.value?.category ?? categories.value[0]?.label;
    }
}

function pick(field) {
    emit('update:modelValue', field.key);
    open.value = false;
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
            class="flex w-full items-center gap-2 rounded-md border border-neutral-100 bg-neutral-0 px-3 py-2.5 text-left text-meta text-neutral-900 transition-colors hover:border-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="toggle"
        >
            <span class="flex-1 truncate">
                <span class="text-neutral-500">{{ current?.category }} / </span>{{ current?.label }}
            </span>
            <Icon :icon="ArrowDown01Icon" class="size-3.5 shrink-0 text-neutral-500" />
        </button>

        <div v-if="open" class="absolute left-0 z-50 mt-2 flex w-96 overflow-hidden rounded-lg border border-neutral-50 bg-neutral-0 shadow-card">
            <ul class="w-1/2 border-r border-neutral-50 py-1">
                <li v-for="category in categories" :key="category.label">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2 text-left text-meta transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                        :class="category.label === activeCategory ? 'bg-neutral-25 text-accent-500' : 'text-neutral-700 hover:bg-neutral-25'"
                        @mouseenter="activeCategory = category.label"
                        @click="activeCategory = category.label"
                    >
                        {{ category.label }}
                        <Icon :icon="ArrowRight01Icon" class="size-3.5 shrink-0 text-neutral-500" />
                    </button>
                </li>
            </ul>
            <ul class="max-h-72 w-1/2 overflow-y-auto py-1">
                <li v-for="field in activeFields" :key="field.key">
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-meta transition-colors focus-visible:bg-neutral-25 focus-visible:outline-none"
                        :class="field.key === modelValue ? 'text-accent-500' : 'text-neutral-700 hover:bg-neutral-25'"
                        @click="pick(field)"
                    >
                        {{ field.label }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
