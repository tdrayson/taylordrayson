<script setup>
import { ref } from 'vue';
import { Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    field: { type: Object, required: true },
    modelValue: { type: [Array, String], default: () => [] },
    mode: { type: String, default: 'edit' },
    error: { type: String, default: null },
});

const emit = defineEmits(['update:modelValue']);

const draft = ref('');

function tags() {
    return Array.isArray(props.modelValue) ? props.modelValue : [];
}

function add() {
    const value = draft.value.trim();
    if (value && !tags().includes(value)) {
        emit('update:modelValue', [...tags(), value]);
    }
    draft.value = '';
}

function remove(tag) {
    emit('update:modelValue', tags().filter((existing) => existing !== tag));
}
</script>

<template>
    <div class="flex flex-col gap-1.5">
        <label class="text-label font-medium text-neutral-700">{{ field.label }}</label>
        <div
            class="flex flex-wrap items-center gap-1.5 rounded-md border bg-neutral-0 px-2 py-1.5"
            :class="error ? 'border-red-500' : 'border-neutral-100'"
        >
            <span
                v-for="tag in tags()"
                :key="tag"
                class="flex items-center gap-1 rounded-md bg-neutral-25 px-2 py-1 text-caption text-neutral-700"
            >
                {{ tag }}
                <button type="button" aria-label="Remove" @click="remove(tag)">
                    <Icon :icon="Cancel01Icon" class="size-3 text-neutral-500 hover:text-neutral-900" />
                </button>
            </span>
            <input
                v-model="draft"
                type="text"
                placeholder="Add tag"
                class="min-w-24 flex-1 bg-transparent px-1 py-1 text-meta text-neutral-900 placeholder:text-neutral-500 focus:outline-none"
                @keydown.enter.prevent="add"
                @keydown.,.prevent="add"
                @blur="add"
            >
        </div>
        <p v-if="error" class="text-caption text-red-600">{{ error }}</p>
    </div>
</template>
