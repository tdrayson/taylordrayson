<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import DropdownMenu from '../Ui/DropdownMenu.vue';
import { entryType } from '../../entryTypes.js';

/**
 * A shortcut to the things worth logging in the moment, for a signed-in browser
 * only. FloatingActions places it in the bottom-right corner.
 *
 * It exists for the iOS home-screen app, which has no address bar: without it
 * the only route to a form is remembering to navigate to /new. The three types
 * are the ones captured while standing somewhere rather than sitting down.
 */
const page = usePage();
const signedIn = computed(() => page.props.signedIn === true);

// Labelled and iconed from the shared type map, so these rows cannot drift
// from how the same types are drawn everywhere else.
const shortcuts = [
    ...['note', 'fuel', 'event'].map((type) => ({
        label: entryType(type).label,
        icon: entryType(type).icon,
        href: `/new/${type}`,
    })),
    { label: 'Other', href: '/new', icon: 'PlusSignIcon' },
];
</script>

<template>
    <div v-if="signedIn" class="relative">
        <DropdownMenu :items="shortcuts" label="Add an entry" align="right" width-class="w-48" panel-class="bottom-full mb-3">
            <template #trigger="{ open, toggle }">
                <button
                    type="button"
                    class="flex size-12 items-center justify-center rounded-full border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-card transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:border-accent-500 focus-visible:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                    :aria-label="open ? 'Close the add menu' : 'Add an entry'"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    @click="toggle"
                >
                    <Icon
                        name="PlusSignIcon"
                        class="size-5 transition-transform"
                        :class="open && 'rotate-45'"
                    />
                </button>
            </template>
        </DropdownMenu>
    </div>
</template>
