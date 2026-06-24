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
    <div ref="root" class="relative">
        <button
            type="button"
            class="flex w-full items-center gap-2 rounded-md border border-line bg-canvas px-3 py-2.5 text-left text-meta text-ink transition-colors hover:border-accent"
            @click="toggle"
        >
            <span class="flex-1 truncate">
                <span class="text-ink-3">{{ current?.category }} / </span>{{ current?.label }}
            </span>
            <Icon :icon="ArrowDown01Icon" class="size-3.5 shrink-0 text-ink-3" />
        </button>

        <div v-if="open" class="absolute left-0 z-50 mt-2 flex w-96 overflow-hidden rounded-lg border border-line-2 bg-canvas shadow-card">
            <ul class="w-1/2 border-r border-line-2 py-1">
                <li v-for="category in categories" :key="category.label">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between px-4 py-2 text-left text-meta transition-colors"
                        :class="category.label === activeCategory ? 'bg-surface text-accent' : 'text-ink-2 hover:bg-surface'"
                        @mouseenter="activeCategory = category.label"
                        @click="activeCategory = category.label"
                    >
                        {{ category.label }}
                        <Icon :icon="ArrowRight01Icon" class="size-3.5 shrink-0 text-ink-3" />
                    </button>
                </li>
            </ul>
            <ul class="max-h-72 w-1/2 overflow-y-auto py-1">
                <li v-for="field in activeFields" :key="field.key">
                    <button
                        type="button"
                        class="block w-full px-4 py-2 text-left text-meta transition-colors"
                        :class="field.key === modelValue ? 'text-accent' : 'text-ink-2 hover:bg-surface'"
                        @click="pick(field)"
                    >
                        {{ field.label }}
                    </button>
                </li>
            </ul>
        </div>
    </div>
</template>
