<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Home01Icon,
    Clock01Icon,
    HeartCheckIcon,
    AirplaneTakeOff01Icon,
    QuillWrite01Icon,
    PlayCircleIcon,
    Compass01Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import { entryTypes } from '../../entryTypes.js';
import SidebarNavItem from './SidebarNavItem.vue';
import SidebarSection from './SidebarSection.vue';

// A data type's nav entry: archive href from the registry, plural label.
// Children render text-only, so no icon is carried over.
function typeLink(type, label) {
    return { label, href: entryTypes[type].href };
}

// Always-visible top-level destinations.
const topLinks = [
    { label: 'Timeline', href: '/', icon: Home01Icon },
    { label: 'Now', href: '/now', icon: Clock01Icon },
];

// Shopify-style parent items: one row each, children revealed inline.
// Every href is a real, registered route.
const sections = [
    {
        id: 'health',
        label: 'Health',
        icon: HeartCheckIcon,
        links: [typeLink('activity', 'Activities'), typeLink('sleep', 'Sleep'), typeLink('calorie', 'Food')],
    },
    {
        id: 'travel',
        label: 'Travel',
        icon: AirplaneTakeOff01Icon,
        links: [typeLink('flight', 'Flights'), typeLink('checkin', 'Places'), typeLink('fuel', 'Fuel')],
    },
    {
        id: 'writing',
        label: 'Writing',
        icon: QuillWrite01Icon,
        links: [typeLink('article', 'Articles'), typeLink('note', 'Notes'), typeLink('project', 'Projects')],
    },
    {
        id: 'media',
        label: 'Media',
        icon: PlayCircleIcon,
        links: [
            typeLink('media', 'Watched & Played'),
            typeLink('event', 'Events'),
            typeLink('podcast', 'This Week With'),
            typeLink('appearance', 'Appearances'),
        ],
    },
    {
        id: 'explore',
        label: 'Explore',
        icon: Compass01Icon,
        links: [
            { label: 'Stories', href: '/stories' },
            { label: 'Photos', href: '/photos' },
            { label: 'Leaderboard', href: '/leaderboard' },
            { label: 'Feeds', href: '/feeds' },
        ],
    },
];

const page = usePage();

// Current path with any query string dropped, so /articles?page=2 still matches.
const currentPath = computed(() => page.url.split('?')[0]);

/**
 * A link is active on its own page or any sub-page (/flights/ba-123), but a
 * bare prefix is not enough: /sleep must not light up on /sleep-score.
 *
 * @param {string} href The nav link's path.
 * @returns {boolean}
 */
function isActive(href) {
    if (href === '/') {
        return currentPath.value === '/';
    }

    return currentPath.value === href || currentPath.value.startsWith(`${href}/`);
}

// Whether any child of a section is the current page (auto-expands the group).
function sectionActive(section) {
    return section.links.some((link) => isActive(link.href));
}
</script>

<template>
    <nav aria-label="Primary" class="flex flex-col gap-1.5 md:gap-0.5">
        <SidebarNavItem
            v-for="item in topLinks"
            :key="item.href"
            :href="item.href"
            :label="item.label"
            :icon="item.icon"
            :active="isActive(item.href)"
        />

        <SidebarSection
            v-for="section in sections"
            :key="section.id"
            :id="section.id"
            :label="section.label"
            :icon="section.icon"
            :active="sectionActive(section)"
        >
            <SidebarNavItem
                v-for="item in section.links"
                :key="item.href"
                :href="item.href"
                :label="item.label"
                :active="isActive(item.href)"
            />
        </SidebarSection>
    </nav>
</template>
