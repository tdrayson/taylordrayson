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
