<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, required: true },
    // A locally stored path. Remote URLs never reach here: the photo is
    // downloaded at verification time so no reader requests a stranger's server.
    photo: { type: String, default: null },
    size: { type: String, default: 'md' },
    // Mine is the only transparent photo here, so it is the only one whose
    // backing disc is ever visible.
    mine: { type: Boolean, default: false },
});

const SIZES = {
    sm: 'size-8 text-xs',
    md: 'size-9 text-xs',
};

// My crop is transparent, so this disc is what gives it an edge. neutral-25
// is the same colour as the surface a row of mine carries, which would leave
// the cutout floating; accent-100 reads as a disc in both modes, the way the
// profile card's avatar does.
const disc = computed(() => (props.mine ? 'bg-accent-100' : 'bg-neutral-25'));

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
        :class="[SIZES[size] ?? SIZES.md, disc, 'shrink-0 rounded-full object-cover']"
    >

    <span
        v-else
        aria-hidden="true"
        :class="[SIZES[size] ?? SIZES.md, disc, 'flex shrink-0 items-center justify-center rounded-full font-semibold text-neutral-700']"
    >{{ initials }}</span>
</template>
