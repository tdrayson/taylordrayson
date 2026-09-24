<script setup>
import Avatar from '../Profile/Avatar.vue';
import SocialLinks from '../Profile/SocialLinks.vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Heading from '../Ui/Heading.vue';

defineProps({
    name: { type: String, default: 'Taylor Drayson' },
    // Story-specific, longer than the profile card's: intentionally its own
    // wording, not identity.bio.
    bio: {
        type: String,
        default: 'I build stuff on the internet, track everything, and drink too much coffee. This is one of the data stories I keep about my own life.',
    },
});
</script>

<template>
    <aside aria-label="About the author" class="mt-14 flex flex-col gap-5 rounded-xl border border-neutral-50 bg-neutral-25 p-6 sm:flex-row sm:items-start sm:gap-6 sm:p-8">
        <Avatar size="size-14" alt="" class="shrink-0" />
        <div class="min-w-0">
            <Eyebrow as="p" class="text-neutral-400">Written by</Eyebrow>
            <Heading as="p" size="title" class="mt-1 text-neutral-900">{{ name }}</Heading>
            <p class="mt-2 max-w-md text-sm text-neutral-600">{{ bio }}</p>
            <div class="mt-4">
                <SocialLinks />
            </div>
        </div>
    </aside>
</template>
