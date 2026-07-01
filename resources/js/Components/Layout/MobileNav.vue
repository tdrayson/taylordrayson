<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu01Icon, Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';
import StatusBar from './StatusBar.vue';
import SidebarNav from './SidebarNav.vue';
import SocialLinks from '../Profile/SocialLinks.vue';
import Avatar from '../Profile/Avatar.vue';
import StreakBadge from '../Now/StreakBadge.vue';

const open = ref(false);

// The shell becomes the full-screen overlay while the menu is open, and stays
// fixed through the closing animation so the body can ease out without the
// header jumping back into page flow. Toggled off once the body has left.
const shellFixed = ref(false);
const page = usePage();

watch(
    () => page.url,
    () => {
        open.value = false;
    }
);

watch(open, (value) => {
    if (value) {
        shellFixed.value = true;
    }

    document.body.style.overflow = value ? 'hidden' : '';
});

function onAfterLeave() {
    shellFixed.value = false;
}

function onKeydown(event) {
    if (event.key === 'Escape') {
        open.value = false;
    }
}

onMounted(() => document.addEventListener('keydown', onKeydown));

onUnmounted(() => {
    document.removeEventListener('keydown', onKeydown);
    document.body.style.overflow = '';
});
</script>

<template>
    <div class="md:hidden" :class="shellFixed ? 'fixed inset-0 z-50 flex flex-col bg-neutral-0' : ''">
        <header class="flex flex-none items-center justify-between border-b border-neutral-50 px-5 py-3">
            <Link href="/" class="flex items-center gap-2.5">
                <Avatar size="size-8" alt="" />
                <span class="font-display text-lg font-extrabold tracking-tight">Taylor Drayson</span>
            </Link>
            <button
                type="button"
                class="flex text-neutral-700 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                :aria-expanded="open"
                aria-controls="mobile-menu"
                :aria-label="open ? 'Close menu' : 'Open menu'"
                @click="open = !open"
            >
                <Icon :icon="open ? Cancel01Icon : Menu01Icon" class="size-6" />
            </button>
        </header>

        <Transition name="menu-body" @after-leave="onAfterLeave">
            <div v-if="open" id="mobile-menu" class="flex flex-1 flex-col overflow-y-auto px-5 py-6">
                <SidebarNav />
                <div class="menu-foot mt-auto space-y-5 border-t border-neutral-50 pt-6">
                    <StatusBar />
                    <SocialLinks />
                    <StreakBadge />
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
/* The header stays put; only the body animates. It eases out on close, and
   its nav items + footer stagger in on open. */
.menu-body-leave-active {
    transition: opacity 0.18s ease, transform 0.18s ease;
}

.menu-body-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}

#mobile-menu :deep(nav > a),
#mobile-menu .menu-foot {
    animation: menu-item-in 0.38s cubic-bezier(0.16, 1, 0.3, 1) both;
}

#mobile-menu :deep(nav > a:nth-child(1)) {
    animation-delay: 0.04s;
}

#mobile-menu :deep(nav > a:nth-child(2)) {
    animation-delay: 0.08s;
}

#mobile-menu :deep(nav > a:nth-child(3)) {
    animation-delay: 0.12s;
}

#mobile-menu :deep(nav > a:nth-child(4)) {
    animation-delay: 0.16s;
}

#mobile-menu :deep(nav > a:nth-child(5)) {
    animation-delay: 0.2s;
}

#mobile-menu :deep(nav > a:nth-child(6)) {
    animation-delay: 0.24s;
}

#mobile-menu :deep(nav > a:nth-child(7)) {
    animation-delay: 0.28s;
}

#mobile-menu .menu-foot {
    animation-delay: 0.32s;
}

@keyframes menu-item-in {
    from {
        opacity: 0;
        transform: translateY(10px);
    }

    to {
        opacity: 1;
        transform: none;
    }
}

@media (prefers-reduced-motion: reduce) {
    .menu-body-leave-active {
        transition: opacity 0.18s ease;
    }

    .menu-body-leave-to {
        transform: none;
    }

    #mobile-menu :deep(nav > a),
    #mobile-menu .menu-foot {
        animation: none;
    }
}
</style>
