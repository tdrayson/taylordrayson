<script setup>
import { Link } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });
</script>

<template>
    <AppHead :og="{ title: 'Unsubscribed' }" />

    <div class="max-w-2xl">
        <h1 class="font-display text-display">Unsubscribed</h1>

        <p class="mt-3 text-body text-neutral-700">
            You will not get any more emails about replies to that comment. Your
            comment is still there, and your address is not used for anything else.
        </p>

        <p class="mt-6">
            <Link href="/" class="text-meta text-accent-500 underline underline-offset-2">Back to the timeline</Link>
        </p>
    </div>
</template>
