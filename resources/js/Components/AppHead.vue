<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

/**
 * Per-view document head: title plus description, canonical, Open Graph, and
 * Twitter meta. Driven by a single `og` object (built server-side by
 * App\Support\OgMeta) so the copy lives in one place. Every tag carries a
 * `head-key` so Inertia replaces (rather than duplicates) it on each client-side
 * navigation, keeping the head in sync with the current view.
 */
const props = defineProps({
    og: { type: Object, default: () => ({}) },
});

const SITE_NAME = 'Taylor Drayson';
const DEFAULT_DESCRIPTION =
    'I build things on the internet, track everything, and drink too much coffee. A living archive of what I make, watch, read, and get up to.';

const page = usePage();

// The view's metadata, with defaults applied so a partial `og` still renders.
const meta = computed(() => ({
    title: null,
    description: DEFAULT_DESCRIPTION,
    heading: null,
    eyebrow: null,
    accent: null,
    image: null,
    variant: null,
    type: 'website',
    noindex: false,
    ...props.og,
}));

// Absolute base URL, sourced from the server-shared appUrl so og:url/og:image
// resolve correctly during SSR (where window is undefined), with a browser
// fallback for safety.
const origin = computed(() => {
    const shared = page.props.appUrl;

    if (shared) {
        return shared;
    }

    return typeof window === 'undefined' ? '' : window.location.origin;
});

const canonical = computed(() => `${origin.value}${page.url}`);
const fullTitle = computed(() => (meta.value.title ? `${meta.value.title} · ${SITE_NAME}` : SITE_NAME));

// An explicit image wins; otherwise build the generated OG card URL from the
// card heading (falling back to the title), eyebrow, accent, and variant.
const imageUrl = computed(() => {
    if (meta.value.image) {
        return meta.value.image.startsWith('http') ? meta.value.image : `${origin.value}${meta.value.image}`;
    }

    const params = new URLSearchParams({ title: meta.value.heading ?? meta.value.title ?? SITE_NAME });

    if (meta.value.eyebrow) {
        params.set('eyebrow', meta.value.eyebrow);
    }

    if (meta.value.accent) {
        params.set('accent', meta.value.accent);
    }

    if (meta.value.variant) {
        params.set('variant', meta.value.variant);
    }

    return `${origin.value}/og.png?${params.toString()}`;
});
</script>

<template>
    <Head :title="meta.title">
        <meta head-key="description" name="description" :content="meta.description" />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <meta v-if="meta.noindex" head-key="robots" name="robots" content="noindex, nofollow" />

        <meta head-key="og:type" property="og:type" :content="meta.type" />
        <meta head-key="og:site_name" property="og:site_name" :content="SITE_NAME" />
        <meta head-key="og:title" property="og:title" :content="fullTitle" />
        <meta head-key="og:description" property="og:description" :content="meta.description" />
        <meta head-key="og:url" property="og:url" :content="canonical" />
        <meta head-key="og:image" property="og:image" :content="imageUrl" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="meta.description" />
        <meta head-key="twitter:image" name="twitter:image" :content="imageUrl" />
    </Head>
</template>
