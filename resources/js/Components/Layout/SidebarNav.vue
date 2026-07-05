<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Home01Icon,
    Clock01Icon,
    Image01Icon,
    BookOpen01Icon,
    ChampionIcon,
    RssIcon,
} from '@hugeicons-pro/core-stroke-rounded';
import { entryTypes } from '../../entryTypes.js';
import SidebarNavItem from './SidebarNavItem.vue';
import SidebarSection from './SidebarSection.vue';

// A data type's nav entry: icon + archive href from the registry, plural label.
function typeLink(type, label) {
    return { label, href: entryTypes[type].href, icon: entryTypes[type].icon };
}

// Always-visible top-level destinations.
const topLinks = [
    { label: 'Timeline', href: '/', icon: Home01Icon },
    { label: 'Now', href: '/now', icon: Clock01Icon },
];

// Dashboard-style groups; every href is a real, registered route.
const sections = [
    {
        id: 'health',
        label: 'Health',
        links: [typeLink('activity', 'Activities'), typeLink('sleep', 'Sleep'), typeLink('calorie', 'Food')],
    },
    {
        id: 'travel',
        label: 'Travel',
        links: [typeLink('flight', 'Flights'), typeLink('checkin', 'Places'), typeLink('fuel', 'Fuel')],
    },
    {
        id: 'writing',
        label: 'Writing',
        links: [typeLink('article', 'Articles'), typeLink('note', 'Notes'), typeLink('project', 'Projects')],
    },
    {
        id: 'media',
        label: 'Media',
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
        links: [
            { label: 'Stories', href: '/stories', icon: BookOpen01Icon },
            { label: 'Photos', href: '/photos', icon: Image01Icon },
            { label: 'Leaderboard', href: '/leaderboard', icon: ChampionIcon },
            { label: 'Feeds', href: '/feeds', icon: RssIcon },
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
</script>

<template>
    <nav aria-label="Primary" class="flex flex-col">
        <div class="flex flex-col gap-1.5 md:gap-0.5">
            <SidebarNavItem
                v-for="item in topLinks"
                :key="item.href"
                :href="item.href"
                :label="item.label"
                :icon="item.icon"
                :active="isActive(item.href)"
            />
        </div>

        <SidebarSection
            v-for="section in sections"
            :key="section.id"
            :id="section.id"
            :label="section.label"
            class="mt-5"
        >
            <SidebarNavItem
                v-for="item in section.links"
                :key="item.href"
                :href="item.href"
                :label="item.label"
                :icon="item.icon"
                :active="isActive(item.href)"
            />
        </SidebarSection>
    </nav>
</template>
