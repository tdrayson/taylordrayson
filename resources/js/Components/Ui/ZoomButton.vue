<script setup>
import { ZoomInAreaIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

// The zoom chip shown over an image. Place inside a `group/zoom relative` parent
// and position it (e.g. `absolute right-2 top-2 opacity-0
// group-hover/zoom:opacity-100`); the parent owns the click/lightbox behaviour.
</script>

<template>
    <span class="flex size-7 items-center justify-center rounded-md bg-neutral-900/55 text-neutral-0">
        <Icon :icon="ZoomInAreaIcon" class="size-4" />
    </span>
</template>
