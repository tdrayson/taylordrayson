<script setup>
defineProps({
    number: { type: String, required: true },
    kicker: { type: String, default: null },
});
</script>

<template>
    <section
        :id="`chapter-${number}`"
        data-story-chapter
        :data-number="number"
        :data-kicker="kicker"
        class="scroll-mt-8 py-8 first:pt-0 sm:scroll-mt-24 sm:py-10"
    >
        <p class="flex items-center gap-2 text-label uppercase text-neutral-400">
            <span>{{ number }}</span>
            <span v-if="kicker" class="text-neutral-300">/</span>
            <span v-if="kicker">{{ kicker }}</span>
        </p>
        <h2 class="mt-3 max-w-2xl font-display text-name text-neutral-900">
            <slot name="title" />
        </h2>
        <!-- Prose paragraphs stay a readable width; charts, stats and notes fill
             the column so the section never looks narrower than its separator. -->
        <div class="mt-4 space-y-4 text-body text-neutral-600 [&>p]:max-w-reading [&_strong]:font-semibold [&_strong]:text-neutral-900">
            <slot />
        </div>
    </section>
</template>
