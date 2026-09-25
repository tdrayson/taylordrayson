<script setup>
import '@fontsource-variable/montserrat';
import '@fontsource-variable/open-sans';
import '@fontsource/bebas-neue';
import PageSwap from '../Components/LinkPage/PageSwap.vue';
import TinkerBanner from '../Components/LinkPage/TinkerBanner.vue';

// Persistent, so the banner is one element that grows and shrinks between the card and its form.
defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="relative min-h-dvh bg-neutral-25 font-open-sans dark:bg-neutral-0">
        <TinkerBanner :compact="compact" />
        <main>
            <PageSwap>
                <slot />
            </PageSwap>
        </main>
    </div>
</template>
