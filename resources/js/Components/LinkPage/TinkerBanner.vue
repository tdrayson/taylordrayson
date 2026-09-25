<script setup>
import TinkerIllustration from './TinkerIllustration.vue';

defineProps({
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <header :class="compact ? 'h-20' : 'h-36 sm:h-52'" class="relative overflow-hidden bg-tinker-500 text-white">
        <TinkerIllustration :compact="compact" />
        <slot />
    </header>
</template>
