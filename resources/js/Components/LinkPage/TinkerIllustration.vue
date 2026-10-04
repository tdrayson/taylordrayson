<script setup>
import TinkerLogo from './TinkerLogo.vue';
</script>

<template>
    <!-- The logo at two scales: a smaller whole one on the left, a large one cropped to its bulb on the right. -->
    <div class="pointer-events-none absolute inset-0 text-white/15" aria-hidden="true">
        <TinkerLogo class="absolute -left-28 top-12 h-64 sm:left-1/12 sm:h-84" />
        <TinkerLogo class="absolute -right-7 -top-15 h-84 sm:right-1/8 sm:-top-26 sm:h-144" />
    </div>
</template>
