<script setup>
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
        <SidebarNav />
        <div class="mt-auto hidden space-y-4 pt-8 md:block">
            <div class="flex flex-col gap-0.5">
                <SearchBar />
                <button
                    type="button"
                    aria-label="Open settings"
                    class="-ml-3 flex w-[calc(100%+0.75rem)] items-center gap-3 rounded-md px-3 py-2 text-sm font-medium text-neutral-700 transition-colors hover:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="openSettings"
                >
                    <Icon name="Settings01Icon" class="size-5 flex-none text-neutral-500" />
                    Settings
                </button>
            </div>
            <StreakBadge />
        </div>
    </aside>
</template>
