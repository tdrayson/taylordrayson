<script setup>
import { computed, provide, toRef } from 'vue';

const props = defineProps({
    // 'tinted' washes each segment in its colour, 'icons' keeps the box neutral
    // and colours only the glyph, 'plain' is one grey throughout, and 'bare'
    // drops the box altogether for a row with nothing worth framing.
    variant: { type: String, default: 'tinted' },
    size: { type: String, default: 'md' },
});

// Segments read these rather than taking them as props, so a row of them can
// never disagree about how it is drawn.
provide('countGroup', { variant: toRef(props, 'variant'), size: toRef(props, 'size') });

/** Unboxed segments have no edges to join them, so they need space between them instead. */
const gap = computed(() => (props.variant === 'bare' ? (props.size === 'sm' ? 'gap-4' : 'gap-5') : null));
</script>

<template>
    <div role="group" :class="['inline-flex items-stretch', gap]">
        <slot />
    </div>
</template>
