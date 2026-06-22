<script setup>
import { Link } from '@inertiajs/vue3';
import SocialLinks from './SocialLinks.vue';

defineProps({
    name: { type: String, default: 'Taylor Drayson' },
    bio: {
        type: String,
        default: 'I build stuff on the internet, track everything, and drink too much coffee.',
    },
    avatar: { type: String, default: '/headshot-taylor.jpg' },
});
</script>

<template>
    <div class="h-card">
        <Link href="/" aria-label="Home" class="group mb-3 block w-fit">
            <div class="size-13 overflow-hidden rounded-full bg-linear-to-br from-accent to-orange-300 group-hover:animate-avatar-boop">
                <img v-if="avatar" :src="avatar" :alt="name" class="u-photo size-full object-cover" >
            </div>
        </Link>
        <h1 class="mb-2 font-display text-name">
            <Link href="/" class="p-name u-url">{{ name }}</Link>
        </h1>
        <p class="mb-4 max-w-50 p-note text-caption text-ink-3">{{ bio }}</p>
        <SocialLinks />
    </div>
</template>
