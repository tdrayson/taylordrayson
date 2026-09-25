<script setup>
import TinkerLogo from './TinkerLogo.vue';

defineProps({
    // The short banner above the details form shows only the lightbulb.
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="pointer-events-none absolute inset-0 text-white/15" aria-hidden="true">
        <!-- The logo at two scales: a smaller whole one on the left, a large one cropped to its bulb on the right. -->
        <TinkerLogo v-if="!compact" class="absolute -left-28 top-12 h-64 sm:left-1/12 sm:h-84" />
        <TinkerLogo
            class="absolute"
            :class="compact ? '-right-6 -top-12 h-64 sm:right-1/12' : '-right-7 -top-15 h-84 sm:right-1/8 sm:-top-26 sm:h-144'"
        />
    </div>
</template>
