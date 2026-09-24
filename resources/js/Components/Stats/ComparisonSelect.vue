<script setup>
import { computed } from 'vue';
import Icon from '../Ui/Icon.vue';
import { useDismissable } from '../../composables/useDismissable.js';

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

const { isOpen: open, root, toggle, close } = useDismissable();
const active = computed(() => options.find((option) => option.mode === props.mode) ?? options[0]);

function choose(mode) {
    close();
    emit('change', mode);
}
</script>

<template>
    <div ref="root" class="relative">
        <button
            type="button"
            class="inline-flex items-center gap-2 rounded-lg border border-neutral-50 px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:border-neutral-100"
            :aria-expanded="open"
            @click="toggle"
        >
            <template v-if="mode === 'none'">No comparison</template>
            <template v-else><span class="text-neutral-500">vs</span> {{ active.label }}</template>
            <Icon name="ArrowDown01Icon" class="size-4 text-neutral-500" />
        </button>

        <template v-if="open">
            <ul class="absolute right-0 z-40 mt-2 w-52 rounded-lg border border-neutral-50 bg-neutral-0 p-2 text-sm font-medium shadow-card">
                <li v-for="option in options" :key="option.mode">
                    <button
                        type="button"
                        class="w-full rounded-md px-3 py-1.5 text-left transition-colors hover:bg-neutral-25"
                        :class="option.mode === mode ? 'font-medium text-neutral-900' : 'text-neutral-700'"
                        @click="choose(option.mode)"
                    >{{ option.label }}</button>
                </li>
            </ul>
        </template>
    </div>
</template>
