<script setup>
import { provide, toRef } from 'vue';

const props = defineProps({
    size: { type: String, default: 'md' },
});

// Segments read the size rather than taking it as a prop, so a row of them can
// never disagree about how it is drawn.
provide('countGroup', { size: toRef(props, 'size') });
</script>

<template>
    <div role="group" class="inline-flex items-stretch">
        <slot />
    </div>
</template>
