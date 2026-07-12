<script setup>
defineProps({
    // Always required: callers only mount this component once a backdrop
    // URL exists (`v-if="thing.backdrop"`), the caller keeps its own plain
    // hero for the null case.
    backdrop: { type: String, required: true },
    logo: { type: String, default: null },
    // Omit entirely for a purely decorative backdrop (e.g. the film detail
    // page, which already has its own <h1> above). When set, renders the
    // logo image if present, else a heading with this text (visually hidden
    // once a logo takes its place, so exactly one accessible title remains).
    title: { type: String, default: null },
    titleTag: { type: String, default: 'h1' },
    testid: { type: String, default: 'backdrop-hero' },
    // Series/season pages sit directly in the page's content-grid and can
    // bleed to the gutter-inset column; the film detail card is nested a
    // layer deeper (inside Entry.vue's own grid) and stays contained.
    bleed: { type: Boolean, default: true },
});
</script>

<template>
    <section
        :data-testid="testid"
        class="relative aspect-video overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 bg-cover bg-center"
        :class="bleed ? 'full-width-inset' : ''"
        :style="{ backgroundImage: `url(${backdrop})` }"
    >
        <!-- Fixed from-black (not neutral-900), matching PhotoGrid's caption
             scrim: an intentional dark overlay on a photo in both themes. -->
        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

        <div v-if="title" class="relative flex h-full flex-col items-start justify-end gap-2 p-6 sm:p-8">
            <img v-if="logo" :src="logo" alt="" class="max-h-16 max-w-60 object-contain sm:max-h-20">
            <component :is="titleTag" :class="logo ? 'sr-only' : 'font-display text-display text-white'">{{ title }}</component>
        </div>
    </section>
</template>
