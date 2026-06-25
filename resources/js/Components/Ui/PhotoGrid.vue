<script setup>
defineProps({
    photos: { type: Array, required: true },
});
</script>

<template>
    <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-4">
        <div
            v-for="(photo, index) in photos"
            :key="index"
            class="aspect-square overflow-hidden rounded-lg bg-neutral-25"
            :style="photo.gradient ? { background: photo.gradient } : {}"
        >
            <img v-if="photo.src" :src="photo.src" alt="" class="size-full object-cover" >
        </div>
    </div>
</template>
