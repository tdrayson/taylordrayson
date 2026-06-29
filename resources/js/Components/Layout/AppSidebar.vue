<script setup>
import ProfileCard from '../Profile/ProfileCard.vue';
import SidebarNav from './SidebarNav.vue';
import StreakBadge from '../Now/StreakBadge.vue';
import CpSidebarNav from './CpSidebarNav.vue';
import { Link } from '@inertiajs/vue3';

defineProps({
    mode: { type: String, default: 'public' },
});
</script>

<template>
    <aside class="hidden flex-none flex-col px-6 py-8 md:flex md:sticky md:top-0 md:h-dvh md:w-66 md:overflow-y-auto">
        <template v-if="mode === 'cp'">
            <Link href="/cp" class="mb-6 font-display text-item-title text-neutral-900">Control panel</Link>
            <CpSidebarNav />
        </template>
        <template v-else>
            <ProfileCard class="mb-6" />
            <SidebarNav />
            <div class="mt-auto hidden pt-8 md:block">
                <StreakBadge />
            </div>
        </template>
    </aside>
</template>
