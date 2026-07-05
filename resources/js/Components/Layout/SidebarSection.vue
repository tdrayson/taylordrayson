<script setup>
import { ref, watch } from 'vue';
import { ArrowDown01Icon, ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // Stable key for persisting this group's expanded state.
    id: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Array, Object], required: true },
    // Whether the current page lives inside this group (auto-expands it).
    active: { type: Boolean, default: false },
});

const storageKey = `sidebar-section:${props.id}`;

// Collapsed by default (Shopify-style: one open section at a time in
// practice); the group holding the current page opens itself, and a manual
// toggle sticks across visits.
const open = ref(props.active || (typeof localStorage !== 'undefined' && localStorage.getItem(storageKey) === 'open'));

// Navigating into a child (e.g. via the command palette) reveals its group.
watch(() => props.active, (isActive) => {
    if (isActive) {
        open.value = true;
    }
});

/**
 * Toggle the group and remember the choice.
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
        <!-- Parent row styled like a nav item, with a trailing disclosure chevron. -->
        <button
            type="button"
            class="-mx-3 flex w-full items-center gap-3 rounded-md px-3 py-2.5 text-base font-medium transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 md:py-2 md:text-sm"
            :class="active && !open ? 'font-semibold text-neutral-900' : 'text-neutral-700 hover:bg-neutral-25'"
            :aria-expanded="open"
            :aria-controls="`sidebar-section-${id}`"
            @click="toggle"
        >
            <Icon :icon="icon" class="size-5 flex-none" :class="active ? 'text-accent-500' : 'text-neutral-500'" />
            {{ label }}
            <Icon :icon="open ? ArrowDown01Icon : ArrowRight01Icon" class="ml-auto size-3.5 text-neutral-400" />
        </button>
        <!-- Children indent under a rail aligned with the parent icon. -->
        <div v-show="open" :id="`sidebar-section-${id}`" class="ml-2.5 flex flex-col gap-1.5 border-l border-neutral-50 pl-4 md:gap-0.5">
            <slot />
        </div>
    </div>
</template>
