<script setup>
import { computed, ref, watch } from 'vue';
import { ArrowDown01Icon, Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    modelValue: { type: [String, Number], default: '' },
    options: { type: Array, default: () => [] }, // [{ value, label }]
    placeholder: { type: String, default: 'Select' },
    loading: { type: Boolean, default: false },
    invalid: { type: Boolean, default: false },
    clearable: { type: Boolean, default: true },
});

const emit = defineEmits(['update:modelValue', 'search']);

const open = ref(false);
const query = ref('');
const active = ref(0);
const root = ref(null);

const selected = computed(() => props.options.find((option) => option.value === props.modelValue) ?? null);
const buttonLabel = computed(() => selected.value?.label ?? '');

watch(query, (value) => emit('search', value));

function choose(option) {
    emit('update:modelValue', option.value);
    open.value = false;
    query.value = '';
}

function clear() {
    emit('update:modelValue', '');
    query.value = '';
}

function toggle() {
    open.value = !open.value;
    if (open.value) {
        query.value = '';
        active.value = 0;
    }
}

function move(step) {
    if (!props.options.length) {
        return;
    }
    active.value = (active.value + step + props.options.length) % props.options.length;
}

function onClickOutside(event) {
    if (root.value && !root.value.contains(event.target)) {
        open.value = false;
    }
}

watch(open, (value) => {
    if (value) {
        window.addEventListener('mousedown', onClickOutside);
    } else {
        window.removeEventListener('mousedown', onClickOutside);
    }
});

const triggerClasses = computed(() =>
    cn(
        'flex w-full items-center justify-between gap-2 rounded-md border bg-neutral-0 px-3 py-2 text-left text-meta transition-colors focus:outline-none',
        props.invalid ? 'border-red-500' : 'border-neutral-100 focus:border-accent-500',
    ),
);
</script>

<template>
    <div ref="root" class="relative">
        <button type="button" :class="triggerClasses" @click="toggle">
            <span :class="buttonLabel ? 'text-neutral-900' : 'text-neutral-500'" class="truncate">
                {{ buttonLabel || placeholder }}
            </span>
            <span class="flex shrink-0 items-center gap-1">
                <Icon
                    v-if="clearable && modelValue !== '' && modelValue !== null"
                    :icon="Cancel01Icon"
                    class="size-3.5 text-neutral-500 hover:text-neutral-900"
                    role="button"
                    aria-label="Clear"
                    @click.stop="clear"
                />
                <Icon :icon="ArrowDown01Icon" class="size-3.5 text-neutral-500" />
            </span>
        </button>

        <div
            v-if="open"
            class="absolute z-20 mt-1 w-full overflow-hidden rounded-md border border-neutral-100 bg-neutral-0 shadow-card"
        >
            <div class="border-b border-neutral-50 p-2">
                <input
                    v-model="query"
                    type="text"
                    placeholder="Search"
                    autofocus
                    class="w-full rounded-md border border-neutral-100 px-2.5 py-1.5 text-meta text-neutral-900 placeholder:text-neutral-500 focus:border-accent-500 focus:outline-none"
                    @keydown.down.prevent="move(1)"
                    @keydown.up.prevent="move(-1)"
                    @keydown.enter.prevent="options[active] && choose(options[active])"
                    @keydown.esc.prevent="open = false"
                >
            </div>
            <ul class="max-h-60 overflow-y-auto py-1">
                <li v-if="loading" class="px-3 py-2 text-meta text-neutral-500">Loading</li>
                <li v-else-if="!options.length" class="px-3 py-2 text-meta text-neutral-500">No matches</li>
                <li
                    v-for="(option, index) in options"
                    v-else
                    :key="option.value"
                    class="cursor-pointer px-3 py-2 text-meta"
                    :class="[
                        index === active ? 'bg-neutral-25' : '',
                        option.value === modelValue ? 'text-accent-500' : 'text-neutral-900',
                    ]"
                    @mouseenter="active = index"
                    @click="choose(option)"
                >
                    {{ option.label }}
                </li>
            </ul>
        </div>
    </div>
</template>
