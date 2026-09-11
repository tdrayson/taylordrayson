<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Home03Icon,
    Image01Icon,
    BookOpen01Icon,
    UserIcon,
    GridViewIcon,
} from '@hugeicons-pro/core-stroke-rounded';
import { entryTypes } from '../../entryTypes.js';
import SidebarNavItem from './SidebarNavItem.vue';

// Front-of-house only: the destinations with an audience. Every data type
// is reachable from timeline cards, search, and the /more directory; Now
// stays reachable from the status bar's time link.
const links = [
    { label: 'Timeline', href: '/', icon: Home03Icon },
    { label: 'About', href: '/about', icon: UserIcon },
    { label: entryTypes.article.plural, href: entryTypes.article.href, icon: entryTypes.article.icon },
    { label: 'Stories', href: '/stories', icon: BookOpen01Icon },
    { label: 'Photos', href: '/photos', icon: Image01Icon },
    { label: 'More', href: '/more', icon: GridViewIcon },
];

const page = usePage();

// Current path with any query string dropped, so /articles?page=2 still matches.
const currentPath = computed(() => page.url.split('?')[0]);

/**
 * A link is active on its own page or any sub-page (/articles/my-post), but a
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
    <nav aria-label="Primary" class="flex flex-col gap-1.5 md:gap-0.5">
        <SidebarNavItem
            v-for="item in links"
            :key="item.href"
            :href="item.href"
            :label="item.label"
            :icon="item.icon"
            :active="isActive(item.href)"
        />
    </nav>
</template>
