<script setup>
import Icon from './Icon.vue';

// The zoom chip shown over an image. Place inside a `group/zoom relative` parent
// and position it (e.g. `absolute right-2 top-2 opacity-0
// group-hover/zoom:opacity-100`); the parent owns the click/lightbox behaviour.
</script>

<template>
    <!-- Fixed bg-black/text-white (not neutral-900/neutral-0): this chip overlays
         a photo and is an intentional dark surface in both themes. -->
    <span class="flex size-7 items-center justify-center rounded-md bg-black/55 text-white">
        <Icon name="ZoomInAreaIcon" class="size-4" />
    </span>
</template>
