<script setup>
import { onMounted, ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import Leaderboard from '../Components/Snake/Leaderboard.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    entries: { type: Array, default: () => [] },
});

const myFingerprint = ref('');

onMounted(() => {
    try {
        const raw = window.localStorage.getItem('snake-404');
        myFingerprint.value = raw ? (JSON.parse(raw).fingerprint ?? '') : '';
    } catch {
        // Ignore storage/parse failures.
    }
});
</script>

<template>
    <Head title="Leaderboard" />

    <div class="mx-auto max-w-lg">
        <p class="text-eyebrow uppercase text-ink-3">404 Snake</p>
        <h1 class="mt-3 font-display text-display">Leaderboard</h1>
        <p class="mt-4 text-body text-ink-3">
            Every player who found the gap and logged a streak. {{ entries.length }} {{ entries.length === 1 ? 'name' : 'names' }} so far.
        </p>

        <div class="mt-9">
            <Leaderboard :entries="entries" :highlight-fp="myFingerprint" :show-heading="false" />
        </div>

        <div class="mt-9 flex gap-6">
            <!-- Full load so the 404 status page (the game) renders reliably. -->
            <a href="/404" class="text-meta font-medium text-ink underline decoration-line underline-offset-4 transition-colors hover:text-ink-3">
                Back to the game
            </a>
            <Link href="/" class="text-meta font-medium text-ink underline decoration-line underline-offset-4 transition-colors hover:text-ink-3">
                Back to the timeline
            </Link>
        </div>
    </div>
</template>
