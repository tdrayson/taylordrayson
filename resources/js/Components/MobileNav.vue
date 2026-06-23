<script setup>
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import { Menu01Icon, Cancel01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';
import StatusBar from './StatusBar.vue';
import SidebarNav from './SidebarNav.vue';
import SocialLinks from './SocialLinks.vue';
import StreakBadge from './StreakBadge.vue';

const open = ref(false);
const page = usePage();

watch(
    () => page.url,
    () => {
        open.value = false;
    }
);

watch(open, (value) => {
    document.body.style.overflow = value ? 'hidden' : '';
});

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
    <div class="md:hidden">
        <header class="flex items-center justify-between border-b border-line-2 px-5 py-3">
            <Link href="/" class="flex items-center gap-2.5">
                <img src="/headshot-taylor.jpg" alt="" class="size-8 rounded-full object-cover" >
                <span class="font-display text-lg font-extrabold tracking-tight">Taylor Drayson</span>
            </Link>
            <button
                type="button"
                class="flex text-ink-2 transition-colors hover:text-accent"
                :aria-expanded="open"
                aria-controls="mobile-menu"
                aria-label="Open menu"
                @click="open = true"
            >
                <Icon :icon="Menu01Icon" class="size-6" />
            </button>
        </header>

        <Transition name="overlay">
            <div v-if="open" id="mobile-menu" class="fixed inset-0 z-50 flex flex-col bg-canvas">
                <header class="flex items-center justify-between border-b border-line-2 px-5 py-3">
                    <Link href="/" class="flex items-center gap-2.5">
                        <img src="/headshot-taylor.jpg" alt="" class="size-8 rounded-full object-cover" >
                        <span class="font-display text-lg font-extrabold tracking-tight">Taylor Drayson</span>
                    </Link>
                    <button
                        type="button"
                        class="flex text-ink-2 transition-colors hover:text-accent"
                        aria-label="Close menu"
                        @click="open = false"
                    >
                        <Icon :icon="Cancel01Icon" class="size-6" />
                    </button>
                </header>

                <div class="flex flex-1 flex-col overflow-y-auto px-5 py-6">
                    <SidebarNav class="-mx-3" />
                    <div class="mt-auto space-y-5 border-t border-line-2 pt-6">
                        <StatusBar />
                        <SocialLinks />
                        <StreakBadge />
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<style scoped>
.overlay-enter-active,
.overlay-leave-active {
    transition: opacity 0.2s ease;
}

.overlay-enter-from,
.overlay-leave-to {
    opacity: 0;
}
</style>
