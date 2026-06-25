<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import AppLayout from '../Layouts/AppLayout.vue';
import Snake404 from '../Components/Snake/Snake404.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    status: { type: Number, default: 404 },
    entries: { type: Number, default: 0 },
    days: { type: Number, default: 0 },
    leaderboard: { type: Array, default: () => [] },
});

const messages = {
    403: { title: 'This entry is private.', body: "You don't have access to this corner of the log." },
    500: { title: 'Something glitched.', body: 'An error crept into the log. Try again in a moment.' },
    503: { title: 'Briefly offline.', body: 'The log is down for a quick moment. Check back soon.' },
};

const copy = computed(() => {
    if (props.status === 404) {
        return { title: "You weren't supposed to find this." };
    }

    return messages[props.status] ?? messages[500];
});
</script>

<template>
    <Head :title="`${status} Not Found`" />

    <p class="text-eyebrow uppercase text-neutral-500">Error {{ status }}</p>

    <h1 class="mt-3 font-display text-display">{{ copy.title }}</h1>

    <template v-if="status === 404">
        <p class="mt-4 max-w-2xl text-body text-neutral-500">
            Either something broke, or you went poking around for a page that doesn't exist. Either way, bold move. While you're here, I should mention I've logged
            <span class="font-semibold text-neutral-900">{{ entries.toLocaleString() }} entries</span>
            over a
            <span class="font-semibold text-neutral-900">{{ days.toLocaleString() }}-day streak</span>, so I genuinely didn't expect anyone to wander this far off the map.
        </p>
        <p class="mt-4 max-w-2xl text-body text-neutral-500">
            Now that you have, you may as well make yourself useful: the grid below is a game of Snake. Steer with the arrow keys, WASD, or a swipe, and every square you reach fills in a day. Build the longest streak you can, then put your name on the leaderboard. Try not to hit the walls. Or yourself.
        </p>
    </template>
    <p v-else class="mt-4 max-w-xl text-body text-neutral-500">{{ copy.body }}</p>

    <Snake404 v-if="status === 404" class="mt-9" :leaderboard="leaderboard" />
</template>
