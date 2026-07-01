<script setup>
defineProps({
    // Optional citation shown beneath the quote.
    cite: { type: String, default: null },
    // Accent for the left rule: 'default', 'accent', 'fuel', 'food' or 'flight'.
    tone: { type: String, default: 'default' },
});

// Left-rule colour per tone.
const TONES = {
    default: 'border-neutral-200',
    accent: 'border-accent-500',
    fuel: 'border-fuel',
    food: 'border-food',
    flight: 'border-flight',
};
</script>

<template>
    <figure class="my-8 border-l-2 pl-5" :class="TONES[tone] ?? TONES.default">
        <blockquote class="max-w-2xl font-display text-item-title text-neutral-900">
            <slot />
        </blockquote>
        <figcaption v-if="cite" class="mt-3 text-label uppercase text-neutral-500">{{ cite }}</figcaption>
    </figure>
</template>
