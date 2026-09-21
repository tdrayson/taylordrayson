<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import { useDismissable } from '../../lib/editor/dismissable.js';
import { entryType } from '../../entryTypes.js';

/**
 * The one floating control a signed-in browser gets: somewhere to go, and the
 * things worth logging in the moment.
 *
 * It exists for the iOS home-screen app, which has no address bar: without it
 * the only route to HQ or to a form is remembering to type one. The three types
 * are the ones captured while standing somewhere rather than sitting down.
 */
const { isOpen: open, root, close, toggle } = useDismissable();

const page = usePage();

const signedIn = computed(() => page.props.signedIn === true);

// Things waiting in HQ, shared on every page once the queries behind it exist.
const waiting = computed(() => page.props.hubWaiting ?? 0);

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

const label = computed(() => {
    if (open.value) {
        return 'Close the menu';
    }

    return waiting.value ? `Menu, ${waiting.value} waiting on you` : 'Menu';
});
</script>

<template>
    <div v-if="signedIn" ref="root" class="relative">
        <div
            v-if="open"
            class="absolute bottom-full right-0 mb-3 w-48 animate-fade-in rounded-lg border border-neutral-100 bg-neutral-0 py-1 shadow-card"
            role="menu"
        >
            <Link
                href="/hq"
                role="menuitem"
                class="flex min-h-11 items-center gap-3 px-3 text-sm font-medium text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:bg-accent-50 focus-visible:text-accent-700 focus-visible:outline-none"
                @click="close"
            >
                <Icon name="DashboardSquare01Icon" class="size-5 text-neutral-500" />
                HQ
                <span v-if="waiting" class="ml-auto text-2xs font-bold text-accent-500">{{ waiting }}</span>
            </Link>

            <hr class="my-1 border-neutral-50">

            <Link
                v-for="shortcut in shortcuts"
                :key="shortcut.href"
                :href="shortcut.href"
                role="menuitem"
                class="flex min-h-11 items-center gap-3 px-3 text-sm font-medium text-neutral-900 transition-colors hover:bg-accent-50 hover:text-accent-700 focus-visible:bg-accent-50 focus-visible:text-accent-700 focus-visible:outline-none"
                @click="close"
            >
                <Icon :icon="shortcut.icon" class="size-5 text-neutral-500" />
                {{ shortcut.label }}
            </Link>
        </div>

        <button
            type="button"
            class="relative flex size-12 items-center justify-center rounded-full border border-neutral-100 bg-neutral-0 text-neutral-700 shadow-card transition-colors hover:border-accent-500 hover:text-accent-700 focus-visible:border-accent-500 focus-visible:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
            :aria-label="label"
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
    </div>
</template>
