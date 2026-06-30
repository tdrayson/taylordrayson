<script setup>
import Avatar from '../Profile/Avatar.vue';
import SocialLinks from '../Profile/SocialLinks.vue';

defineProps({
    name: { type: String, default: 'Taylor Drayson' },
    bio: {
        type: String,
        default: 'I build stuff on the internet, track everything, and drink too much coffee. This is one of the data stories I keep about my own life.',
    },
});
</script>

<template>
    <aside class="mt-14 flex flex-col gap-5 rounded-xl border border-neutral-50 bg-neutral-25 p-6 sm:flex-row sm:items-start sm:gap-6 sm:p-8">
        <Avatar size="size-14" alt="" class="shrink-0" />
        <div class="min-w-0">
            <p class="text-label uppercase text-neutral-400">Written by</p>
            <p class="mt-1 font-display text-name text-neutral-900">{{ name }}</p>
            <p class="mt-2 max-w-md text-meta text-neutral-600">{{ bio }}</p>
            <div class="mt-4">
                <SocialLinks />
            </div>
        </div>
    </aside>
</template>
