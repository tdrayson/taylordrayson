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
        class="block size-11 overflow-hidden rounded-full border-2 border-neutral-0 shadow-md ring-2 ring-activity transition-transform hover:scale-110 focus-visible:scale-110 focus-visible:outline-none focus-visible:ring-4"
        @click="$emit('select')"
    >
        <img
            :src="photo.src"
            :srcset="photo.srcset || undefined"
            sizes="44px"
            alt=""
            class="size-full object-cover"
        >
    </button>
</template>
