<script setup>
defineProps({
    cite: { type: String, default: null },
});
</script>

<template>
    <figure class="my-8 border-l-2 border-fuel pl-5">
        <blockquote class="max-w-2xl font-display text-item-title text-neutral-900">
            <slot />
        </blockquote>
        <figcaption v-if="cite" class="mt-3 text-label uppercase text-neutral-500">{{ cite }}</figcaption>
    </figure>
</template>
