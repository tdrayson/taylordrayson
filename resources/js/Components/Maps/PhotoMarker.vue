<script setup>
defineProps({
    photo: { type: Object, required: true },
    label: { type: String, required: true },
});

defineEmits(['select']);
</script>

<template>
    <button
        type="button"
        data-testid="photo-marker"
        :aria-label="label"
        class="group block size-11 rounded-full focus-visible:outline-none"
        @click="$emit('select')"
    >
        <!--
            The button itself is the element MapLibre positions, and it does that
            by writing an inline `transform: translate(...)` onto it every time the
            map moves. So the button must carry no transform of its own: the
            hover/focus scale lives on this inner disc instead. Putting the scale on
            the button would overwrite MapLibre's translate and make the marker jump
            to the map origin on hover.
        -->
        <span
            class="block size-full overflow-hidden rounded-full border-2 border-neutral-0 shadow-md ring-2 ring-activity transition-transform group-hover:scale-110 group-focus-visible:scale-110 group-focus-visible:ring-4"
        >
            <img
                :src="photo.src"
                :srcset="photo.srcset || undefined"
                sizes="44px"
                alt=""
                class="size-full object-cover"
            >
        </span>
    </button>
</template>

<style scoped>
/*
    MapLibre positions each marker on its true point and, on a flat map, leaves
    the stacking of overlapping markers to DOM order. When two photos were taken
    close together their markers overlap; raising the hovered or focused one
    lifts that photo clear of its neighbours so it is fully visible and
    clickable. focus-within (not just hover) means a keyboard user can still
    reach and surface a marker sitting underneath another. !important wins over
    any inline z-index MapLibre may set on the marker element itself. The value
    stays at 1: enough to lift above sibling markers (which sit at auto), but
    below MapLibre's own controls (the zoom control is z-index 2) so a raised
    marker near the corner never covers the map buttons.
*/
button:hover,
button:focus-within {
    z-index: 1 !important;
}
</style>
