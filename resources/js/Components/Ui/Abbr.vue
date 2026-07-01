<script setup>
import Tooltip from './Tooltip.vue';

defineProps({
    // The expansion shown in the tooltip and exposed to assistive tech; when
    // null the abbreviation renders plainly with no tooltip.
    title: { type: String, default: null },
    // Tooltip placement, passed through to Tooltip.
    placement: { type: String, default: 'bottom' },
});
</script>

<template>
    <Tooltip v-if="title" :label="title" :placement="placement">
        <abbr
            class="cursor-help underline decoration-neutral-300 decoration-dotted underline-offset-2"
            :aria-label="title"
        ><slot /></abbr>
    </Tooltip>
    <abbr v-else><slot /></abbr>
</template>
