<script setup>
import { ref } from 'vue';
import { ArrowLeft01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    // The human range label for the current selection, e.g. "Jan – Dec 2025".
    label: { type: String, required: true },
});

const emit = defineEmits(['change', 'step']);

// Cosmetic in this prototype: presets and stepping emit intent but do not yet
// refetch. Wiring real per-range data is the follow-up.
const presets = ['Month', 'Year', 'All', 'Custom'];
const active = ref('Year');

function select(preset) {
    active.value = preset;
    emit('change', preset);
}
</script>

<template>
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-lg border border-neutral-50 px-1 py-1">
            <button type="button" class="flex size-7 items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="Previous range" @click="emit('step', -1)">
                <Icon :icon="ArrowLeft01Icon" class="size-4" />
            </button>
            <span class="min-w-40 px-2 text-center text-nav font-medium text-neutral-900">{{ label }}</span>
            <button type="button" class="flex size-7 items-center justify-center rounded-md text-neutral-500 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500" aria-label="Next range" @click="emit('step', 1)">
                <Icon :icon="ArrowRight01Icon" class="size-4" />
            </button>
        </div>

        <div class="inline-flex rounded-lg border border-neutral-50 p-1 text-sm">
            <button
                v-for="preset in presets"
                :key="preset"
                type="button"
                class="rounded-md px-3 py-1 font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :class="preset === active ? 'bg-neutral-25 text-neutral-900' : 'text-neutral-500 hover:text-neutral-900'"
                @click="select(preset)"
            >
                {{ preset }}
            </button>
        </div>
    </div>
</template>
