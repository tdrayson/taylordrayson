<script setup>
import { computed, provide, toRef } from 'vue';

const props = defineProps({
    // 'plain' joins the segments into one grey box, 'bare' drops the box for a
    // row with nothing worth framing.
    variant: { type: String, default: 'plain' },
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
