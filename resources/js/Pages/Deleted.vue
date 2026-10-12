<script setup>
import { Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Heading from '../Components/Ui/Heading.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

/**
 * A deleted post's address, served with 410 Gone.
 *
 * Marked up as an h-entry carrying dt-deleted, so a reader or receiver that
 * ignores the status still replaces its copy with this instead of the post.
 */
defineProps({
    url: { type: String, required: true },
    deletedAt: { type: String, required: true },
    deletedOn: { type: String, required: true },
});
</script>

<template>
    <AppHead />

    <article class="h-entry max-w-2xl">
        <a class="u-url" :href="url" hidden></a>
        <Heading as="h1" size="display" class="p-name">Deleted</Heading>

        <p class="e-content mt-3 text-base text-neutral-700">This post has been deleted.</p>

        <p class="mt-2 text-sm text-neutral-500">
            Deleted on <time class="dt-deleted" :datetime="deletedAt">{{ deletedOn }}</time>.
        </p>

        <p class="mt-6">
            <Link href="/" class="text-sm text-accent-500 underline underline-offset-2">Back to the timeline</Link>
        </p>
    </article>
</template>
