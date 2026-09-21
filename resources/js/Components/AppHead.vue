<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import { ogMeta } from '../lib/og.js';
import { useOgCard } from '../composables/useOgCard.js';

/**
 * Per-view document head: title plus description, canonical, Open Graph, and
 * Twitter meta. Driven by a single `og` object (built server-side by
 * App\Support\OgMeta) so the copy lives in one place. Every tag carries a
 * `head-key` so Inertia replaces (rather than duplicates) it on each client-side
 * navigation, keeping the head in sync with the current view.
 */
const props = defineProps({
    og: { type: Object, default: () => ({}) },
    // [{ extension, type, label, url }] for the resource this view
    // shows, built server-side from Formats::for() so the head only ever
    // advertises a format the resource can actually be rendered as.
    formats: { type: Array, default: () => [] },
});

const page = usePage();

// The one site-identity name, shared from config/identity.php.
const SITE_NAME = computed(() => page.props.identity.name);

const meta = computed(() => ogMeta(props.og));

const imageUrl = useOgCard(() => props.og);

// Absolute base URL, sourced from the server-shared appUrl so og:url resolves
// correctly during SSR (where window is undefined), with a browser fallback.
const origin = computed(() => {
    const shared = page.props.appUrl;

    if (shared) {
        return shared;
    }

    return typeof window === 'undefined' ? '' : window.location.origin;
});

// Feed links narrowed to the current view's timeline type, built server-side by
// App\Support\FeedDiscovery and empty on any view that isn't type-scoped.
const contextualFeeds = computed(() => page.props.contextualFeeds ?? []);

const canonical = computed(() => `${origin.value}${page.url}`);
const fullTitle = computed(() => (meta.value.title ? `${meta.value.title} | ${SITE_NAME.value}` : SITE_NAME.value));
</script>

<template>
    <Head :title="meta.title">
        <meta head-key="description" name="description" :content="meta.description" />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <link
            v-for="feed in contextualFeeds"
            :key="feed.type"
            :head-key="`feed:${feed.type}`"
            rel="alternate"
            :type="feed.type"
            :title="feed.title"
            :href="feed.href"
        />
        <link
            v-for="format in formats"
            :key="format.extension"
            :head-key="`format:${format.extension}`"
            rel="alternate"
            :type="format.type"
            :title="`${meta.title ?? SITE_NAME} (${format.label})`"
            :href="format.url"
        />
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
