<script setup>
import TinkerLogo from './TinkerLogo.vue';

defineProps({
    // The short banner above the details form shows only the lightbulb.
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="pointer-events-none absolute inset-0 text-white/15" aria-hidden="true">
        <!-- The compact banner pins the bulb to the content column, so it sits beside Back at any width. -->
        <div v-if="compact" class="relative mx-auto h-full max-w-sm">
            <TinkerLogo class="absolute -top-7 right-4 h-40" />
        </div>
        <!-- The logo at two scales: a smaller whole one on the left, a large one cropped to its bulb on the right. -->
        <template v-else>
            <TinkerLogo class="absolute -left-28 top-12 h-64 sm:left-1/12 sm:h-84" />
            <TinkerLogo class="absolute -right-7 -top-15 h-84 sm:right-1/8 sm:-top-26 sm:h-144" />
        </template>
    </div>
</template>
