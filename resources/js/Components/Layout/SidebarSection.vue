<script setup>
import { ref } from 'vue';
import { ArrowDown01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // Stable key for persisting this section's collapsed state.
    id: { type: String, required: true },
    label: { type: String, required: true },
});

const storageKey = `sidebar-section:${props.id}`;

// Sections start open; a visitor's collapse choice sticks across visits.
const open = ref(typeof localStorage === 'undefined' || localStorage.getItem(storageKey) !== 'closed');

/**
 * Toggle the section and remember the choice.
 *
 * @returns {void}
 */
function toggle() {
    open.value = !open.value;

    if (typeof localStorage !== 'undefined') {
        localStorage.setItem(storageKey, open.value ? 'open' : 'closed');
    }
}
</script>

<template>
    <div>
        <button
            type="button"
            class="-mx-3 flex w-full items-center justify-between rounded-md px-3 py-1.5 text-label uppercase text-neutral-400 transition-colors hover:text-neutral-600 focus-visible:text-neutral-600 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :aria-expanded="open"
            :aria-controls="`sidebar-section-${id}`"
            @click="toggle"
        >
            {{ label }}
            <Icon :icon="ArrowDown01Icon" class="size-3.5 transition-transform" :class="open ? '' : '-rotate-90'" />
        </button>
        <div v-show="open" :id="`sidebar-section-${id}`" class="mt-0.5 flex flex-col gap-1.5 md:gap-0.5">
            <slot />
        </div>
    </div>
</template>
