<script setup>
import { ref, computed } from 'vue';
import { ArrowDown01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // Current comparison mode: 'previous-period' | 'previous-year' | 'none'.
    mode: { type: String, default: 'previous-period' },
});

// Emits the chosen mode; the parent reloads the dashboard with new deltas.
const emit = defineEmits(['change']);

const options = [
    { label: 'Previous period', mode: 'previous-period' },
    { label: 'Previous year', mode: 'previous-year' },
    { label: 'No comparison', mode: 'none' },
];

const open = ref(false);
const active = computed(() => options.find((option) => option.mode === props.mode) ?? options[0]);

function choose(mode) {
    open.value = false;
    emit('change', mode);
}
</script>

<template>
    <div class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-neutral-50 px-3 py-2 text-nav font-medium text-neutral-700 transition-colors hover:border-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            @click="open = !open"
        >
            <template v-if="mode === 'none'">No comparison</template>
            <template v-else><span class="text-neutral-500">vs</span> {{ active.label }}</template>
            <Icon :icon="ArrowDown01Icon" class="size-4 text-neutral-500" />
        </button>

        <template v-if="open">
            <div class="fixed inset-0 z-30" @click="open = false" />
            <ul class="absolute right-0 z-40 mt-2 w-52 rounded-lg border border-neutral-50 bg-neutral-0 p-2 text-nav shadow-card">
                <li v-for="option in options" :key="option.mode">
                    <button
                        type="button"
                        class="w-full rounded-md px-3 py-1.5 text-left transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                        :class="option.mode === mode ? 'font-medium text-neutral-900' : 'text-neutral-700'"
                        @click="choose(option.mode)"
                    >{{ option.label }}</button>
                </li>
            </ul>
        </template>
    </div>
</template>
