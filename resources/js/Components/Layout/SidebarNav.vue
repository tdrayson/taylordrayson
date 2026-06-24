<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import {
    Home01Icon,
    Clock01Icon,
    Calendar03Icon,
    ChartColumnIcon,
    MapsLocation01Icon,
    UserIcon,
} from '@hugeicons-pro/core-stroke-rounded';
import SidebarNavItem from './SidebarNavItem.vue';

const links = [
    { label: 'Timeline', href: '/', icon: Home01Icon },
    { label: 'Now', href: '/now', icon: Clock01Icon },
    { label: 'Calendar', href: '/calendar', icon: Calendar03Icon },
    { label: 'Stats', href: '/stats', icon: ChartColumnIcon },
    { label: 'Map', href: '/map', icon: MapsLocation01Icon },
    { label: 'About', href: '/about', icon: UserIcon },
];

const page = usePage();

function isActive(href) {
    return href === '/' ? page.url === '/' : page.url.startsWith(href);
}

const items = computed(() =>
    links.map((link) => ({
        ...link,
        active: isActive(link.href),
    }))
);
</script>

<template>
    <nav aria-label="Primary" class="flex flex-col gap-1.5 md:gap-1">
        <SidebarNavItem
            v-for="item in items"
            :key="item.href"
            :href="item.href"
            :label="item.label"
            :icon="item.icon"
            :active="item.active"
        />
    </nav>
</template>
