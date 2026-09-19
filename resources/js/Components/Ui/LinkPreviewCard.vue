<script setup>
import Eyebrow from './Eyebrow.vue';

defineProps({
    // One preview: { url, title, excerpt, type, accent, date, cover, coverDark }.
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
            :class="preview.coverDark ? 'dark:hidden' : ''"
        >

        <img
            v-if="preview.coverDark"
            :src="preview.coverDark"
            alt=""
            loading="lazy"
            class="hidden aspect-video w-full object-cover dark:block"
        >
        <div class="space-y-1.5 p-3">
            <Eyebrow class="flex items-center gap-2 tracking-wide">
                <span class="inline-block size-2 rounded-full" :style="{ backgroundColor: 'var(--type-color)' }" />
                <span class="text-neutral-500">{{ preview.type }}</span>
                <span v-if="preview.date" class="text-neutral-400">{{ preview.date }}</span>
            </Eyebrow>
            <p class="text-lg font-bold leading-tight tracking-tight text-neutral-900">{{ preview.title }}</p>
            <p v-if="preview.excerpt" class="line-clamp-2 text-xs text-neutral-600">{{ preview.excerpt }}</p>
        </div>
    </article>
</template>
