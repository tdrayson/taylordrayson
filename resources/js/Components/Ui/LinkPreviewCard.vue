<script setup>
defineProps({
    // One preview: { url, title, excerpt, type, accent, date, cover }.
    preview: { type: Object, required: true },
});
</script>

<template>
    <article
        class="overflow-hidden rounded-lg border border-neutral-100 bg-neutral-0 shadow-card"
        :style="{ '--type-color': `var(--color-${preview.accent ?? preview.type}, var(--color-neutral-400))` }"
    >
        <img
            v-if="preview.cover"
            :src="preview.cover"
            alt=""
            loading="lazy"
            class="aspect-video w-full object-cover"
        >
        <div class="space-y-1.5 p-3">
            <div class="flex items-center gap-2 text-label uppercase tracking-wide">
                <span class="inline-block size-2 rounded-full" :style="{ backgroundColor: 'var(--type-color)' }" />
                <span class="text-neutral-500">{{ preview.type }}</span>
                <span v-if="preview.date" class="text-neutral-400">{{ preview.date }}</span>
            </div>
            <p class="text-section font-bold leading-tight text-neutral-900">{{ preview.title }}</p>
            <p v-if="preview.excerpt" class="line-clamp-2 text-caption text-neutral-600">{{ preview.excerpt }}</p>
        </div>
    </article>
</template>
