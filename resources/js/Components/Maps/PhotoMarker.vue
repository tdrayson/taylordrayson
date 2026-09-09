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
        class="group block size-11 cursor-zoom-in rounded-full focus-visible:outline-none"
        @click="$emit('select')"
    >
        <!-- The scale lives on this inner disc, not the button: MapLibre writes an
             inline transform onto the button, which any transform of ours would
             overwrite, jumping the marker to the map origin. -->
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
    Overlapping markers stack by DOM order, so the hovered or focused one is
    raised clear of its neighbours. MapLibre gives every marker its own inline
    z-index, so this has to clear those rather than sit at 1; the map's controls
    are lifted above all of them in editor.css. EntryMap isolates the map, so
    none of this escapes to the page.
*/
button:hover,
button:focus-within {
    z-index: 900 !important;
}
</style>
