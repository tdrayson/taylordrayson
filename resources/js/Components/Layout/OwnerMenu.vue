<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import DropdownMenu from '../Ui/DropdownMenu.vue';
import { entryType } from '../../entryTypes.js';

/**
 * The one floating control a signed-in browser gets: somewhere to go, and the
 * things worth logging in the moment.
 *
 * It exists for the iOS home-screen app, which has no address bar: without it
 * the only route to HQ or to a form is remembering to type one. The three types
 * are the ones captured while standing somewhere rather than sitting down.
 */
const page = usePage();

const signedIn = computed(() => page.props.signedIn === true);

// Things waiting in HQ, shared on every page once the queries behind it exist.
const waiting = computed(() => page.props.hubWaiting ?? 0);

// HQ first, carrying its count; the entry types are labelled and iconed from
// the shared type map, so these rows cannot drift from how they are drawn elsewhere.
const items = computed(() => [
    { label: 'HQ', href: '/hq', icon: 'DashboardSquare01Icon', description: waiting.value ? String(waiting.value) : null },
    ...['note', 'fuel', 'event'].map((type) => ({
        label: entryType(type).label,
        icon: entryType(type).icon,
        href: `/new/${type}`,
    })),
    { label: 'Other', href: '/new', icon: 'PlusSignIcon' },
]);

/** The trigger's name, which is also where the waiting count is read out. */
function triggerLabel(open) {
    if (open) {
        return 'Close the menu';
    }

    return waiting.value ? `Menu, ${waiting.value} waiting on you` : 'Menu';
}
</script>

<template>
    <div v-if="signedIn" class="relative">
        <DropdownMenu
            :items="items"
            label="Menu"
            align="right"
            width-class="w-48"
            panel-class="bottom-full mb-3 animate-fade-in rounded-lg"
            item-class="min-h-11 gap-3 px-3 font-medium text-neutral-900 hover:bg-accent-50 hover:text-accent-700 focus-visible:bg-accent-50 focus-visible:text-accent-700"
            icon-class="size-5"
        >
            <template #trigger="{ open, toggle }">
                <button
                    type="button"
                    class="relative flex size-12 items-center justify-center rounded-full border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-card transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:text-accent-700"
                    :aria-label="triggerLabel(open)"
                    aria-haspopup="menu"
                    :aria-expanded="open"
                    @click="toggle"
                >
                    <Icon name="FlashIcon" class="size-5" />

                    <!-- Unlabelled on purpose: the count is read out in aria-label, and a
                         second number beside the one in the menu is noise. -->
                    <span
                        v-if="waiting && ! open"
                        class="absolute right-px top-px size-3 rounded-full bg-accent-500 ring-2 ring-neutral-0"
                        aria-hidden="true"
                    />
                </button>
            </template>
        </DropdownMenu>
    </div>
</template>
