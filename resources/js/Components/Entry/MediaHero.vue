<script setup>
defineProps({
    // Only mounted once a backdrop exists; the caller keeps the null case.
    backdrop: { type: String, required: true },
    // The studio's title treatment, standing in for the page's own heading.
    logo: { type: String, default: null },
    poster: { type: String, default: null },
    // Read out when the logo carries the title, since the image has no text.
    title: { type: String, default: '' },
});
</script>

<template>
    <div class="relative sm:pb-8">
        <section
            data-testid="media-hero"
            class="relative aspect-video overflow-hidden rounded-lg border border-neutral-50 bg-neutral-25 bg-cover bg-center"
            :style="{ backgroundImage: `url(${backdrop})` }"
        >
            <!-- Fixed black, not the neutral ramp: an intentional dark surface in both themes. -->
            <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/30 to-transparent" />

            <img
                v-if="logo"
                :src="logo"
                :alt="title"
                class="absolute bottom-6 left-6 max-h-14 max-w-48 object-contain object-left sm:bottom-8 sm:max-h-20 sm:max-w-72"
                :class="poster ? 'sm:left-56' : 'sm:left-8'"
            >
        </section>

        <!-- Hangs below the backdrop, which is what `pb-*` above leaves room
             for. Hidden on small screens, where it would cover the backdrop. -->
        <img
            v-if="poster"
            :src="poster"
            alt=""
            class="absolute bottom-0 left-8 hidden w-40 rounded-md border border-neutral-50 shadow-card sm:block"
        >
    </div>
</template>
