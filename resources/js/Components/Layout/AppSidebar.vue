<script setup>
import { Settings01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import ProfileCard from '../Profile/ProfileCard.vue';
import SearchBar from './SearchBar.vue';
import SidebarNav from './SidebarNav.vue';
import StreakBadge from '../Now/StreakBadge.vue';
import { useSettings } from '../../useSettings';

const { openSettings } = useSettings();
</script>

<template>
    <aside class="hidden flex-none flex-col px-6 py-8 md:flex md:sticky md:top-0 md:h-dvh md:w-66 md:overflow-y-auto">
        <ProfileCard class="mb-5" />
        <SearchBar class="mb-5" />
        <SidebarNav />
        <div class="mt-auto hidden space-y-4 pt-8 md:block">
            <StreakBadge />
            <button
                type="button"
                aria-label="Open settings"
                class="inline-flex items-center gap-2 rounded-lg px-2 py-1.5 text-nav text-neutral-500 transition hover:bg-neutral-50 hover:text-neutral-900 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                @click="openSettings"
            >
                <Icon :icon="Settings01Icon" class="size-5" />
                Settings
            </button>
        </div>
    </aside>
</template>
