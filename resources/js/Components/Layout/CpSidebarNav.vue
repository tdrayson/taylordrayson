<script setup>
import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import SidebarNavItem from './SidebarNavItem.vue';
import { cpIcon } from '../../cpIcons.js';

const page = usePage();

const groups = computed(() => page.props.cp?.nav ?? []);

function isActive(slug) {
    return page.url.startsWith(`/cp/${slug}`);
}
</script>

<template>
    <nav aria-label="Collections" class="flex flex-col gap-6">
        <div v-for="group in groups" :key="group.group" class="flex flex-col gap-1.5 md:gap-1">
            <p class="px-3 text-eyebrow uppercase text-neutral-500">{{ group.group }}</p>
            <SidebarNavItem
                v-for="item in group.items"
                :key="item.slug"
                :href="`/cp/${item.slug}`"
                :label="item.label"
                :icon="cpIcon(item.slug)"
                :active="isActive(item.slug)"
            />
        </div>
    </nav>
</template>
