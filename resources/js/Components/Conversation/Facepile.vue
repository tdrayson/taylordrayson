<script setup>
import Avatar from './Avatar.vue';

defineProps({
    // FaceData[]: { name, url, photo, emoji } — likes and reacji from elsewhere.
    faces: { type: Array, default: () => [] },
});
</script>

<template>
    <!-- Roomier than a plain avatar row needs to be: each face carries an
         emoji badge that would otherwise sit on top of its neighbour. -->
    <ul v-if="faces.length" class="flex flex-wrap gap-x-3 gap-y-3">
        <li v-for="(face, index) in faces" :key="`${face.url}-${index}`" class="relative">
            <component
                :is="face.url ? 'a' : 'span'"
                :href="face.url || undefined"
                rel="noopener noreferrer nofollow"
                class="block rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                :title="`${face.name} reacted ${face.emoji}`"
            >
                <Avatar :name="face.name" :photo="face.photo" />

                <!-- The emoji actually sent, so a 🚀 stays a 🚀 rather than
                     being rounded to the nearest offered reaction. -->
                <span
                    class="absolute -bottom-1 -right-1 flex size-5 items-center justify-center rounded-full bg-canvas text-caption leading-none"
                    aria-hidden="true"
                >{{ face.emoji }}</span>

                <span class="sr-only">{{ face.name }} reacted {{ face.emoji }}</span>
            </component>
        </li>
    </ul>
</template>
