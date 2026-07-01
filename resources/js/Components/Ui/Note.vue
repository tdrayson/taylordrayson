<script setup>
defineProps({
    label: { type: String, default: 'Note' },
    tone: { type: String, default: 'default' },
});

// The diamond marker and label pick up the tone; the rule and body stay quiet.
const MARKERS = {
    default: 'bg-neutral-900',
    accent: 'bg-accent-500',
    fuel: 'bg-fuel',
    food: 'bg-food',
    flight: 'bg-flight',
};
</script>

<template>
    <aside class="my-7">
        <!-- The diamond hangs in the left margin (like the timeline rail) so the
             label and body align with the rest of the chapter's content. -->
        <div class="relative flex items-center gap-3">
            <span class="absolute -left-5 top-1/2 hidden size-1.5 -translate-y-1/2 rotate-45 sm:block" :class="MARKERS[tone] ?? MARKERS.default" />
            <span class="text-label uppercase tracking-wider text-neutral-900">{{ label }}</span>
            <span class="h-px flex-1 bg-neutral-100" />
        </div>
        <p class="mt-3 text-meta text-neutral-600 [&_strong]:font-semibold [&_strong]:text-neutral-900">
            <slot />
        </p>
    </aside>
</template>
