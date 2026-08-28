<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    // A locally stored path. Remote URLs never reach here: the photo is
    // downloaded at verification time so no reader requests a stranger's server.
    photo: { type: String, default: null },
    size: { type: String, default: 'md' },
});

const SIZES = {
    sm: 'size-8 text-caption',
    md: 'size-9 text-caption',
};

/** Two letters is enough to tell people apart when there is no photo. */
const initials = computed(() => props.name
    .split(/\s+/)
    .slice(0, 2)
    .map((word) => word.charAt(0).toUpperCase())
    .join(''));
</script>

<template>
    <img
        v-if="photo"
        :src="photo"
        alt=""
        loading="lazy"
        decoding="async"
        :class="[SIZES[size] ?? SIZES.md, 'shrink-0 rounded-full bg-neutral-25 object-cover']"
    >

    <span
        v-else
        aria-hidden="true"
        :class="[SIZES[size] ?? SIZES.md, 'flex shrink-0 items-center justify-center rounded-full bg-neutral-25 font-semibold text-neutral-700']"
    >{{ initials }}</span>
</template>
