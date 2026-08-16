<script setup>
import { computed, ref, watch } from 'vue';

/**
 * A set of fields shown as one summary line until opened. The address a lookup
 * filled is usually right, so it reads as a value rather than five inputs.
 */
const props = defineProps({
    label: { type: String, required: true },
    summary: { type: String, default: '' },
    // One of the fields inside was refused by the server.
    invalid: { type: Boolean, default: false },
});

const expanded = ref(false);

// A rejected field cannot be fixed while it is collapsed out of sight.
watch(() => props.invalid, (isInvalid) => {
    if (isInvalid) {
        expanded.value = true;
    }
}, { immediate: true });

const open = computed({
    get: () => expanded.value || props.invalid,
    set: (value) => {
        expanded.value = value;
    },
});
</script>

<template>
    <div class="rounded-lg border bg-neutral-25 p-3" :class="invalid ? 'border-red-500' : 'border-neutral-50'">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-label uppercase text-neutral-500">{{ label }}</p>
                <p v-if="summary" class="mt-0.5 text-meta text-neutral-900">{{ summary }}</p>
                <p v-else class="mt-0.5 text-meta text-neutral-500">Not set</p>
            </div>

            <button
                type="button"
                class="min-h-11 shrink-0 px-2 text-meta text-accent-500 underline underline-offset-2 hover:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                :aria-expanded="open"
                @click="open = ! open"
            >
                {{ open ? 'Done' : 'Edit' }}
            </button>
        </div>

        <div v-if="open" class="mt-3 space-y-4">
            <slot />
        </div>
    </div>
</template>
