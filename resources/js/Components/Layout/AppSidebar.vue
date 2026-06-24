<script setup>
import ProfileCard from '../Profile/ProfileCard.vue';
import SidebarNav from './SidebarNav.vue';
import StreakBadge from '../Now/StreakBadge.vue';
</script>

<template>
    <aside class="hidden flex-none flex-col px-6 py-8 md:flex md:sticky md:top-0 md:h-screen md:w-66 md:overflow-y-auto">
        <ProfileCard class="mb-6" />
        <SidebarNav />
        <div class="mt-auto hidden pt-8 md:block">
            <StreakBadge />
        </div>
    </aside>
</template>
