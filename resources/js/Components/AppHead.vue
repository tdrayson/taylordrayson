<script setup>
import { computed, h } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';

/**
 * The document head, rendered from the `head` payload App\Support\Head shares
 * with every page. Every tag carries a `head-key` so Inertia replaces (rather
 * than duplicates) it on each client-side navigation.
 */
const page = usePage();

// The page's resolved head: title, description, share image, and extra tags.
const head = computed(() => page.props.head);

// The one site-identity name, shared from config/identity.php.
const SITE_NAME = computed(() => page.props.identity.name);

// Absolute base URL, sourced from the server-shared appUrl so og:url resolves
// correctly during SSR (where window is undefined), with a browser fallback.
const origin = computed(() => {
    const shared = page.props.appUrl;

    if (shared) {
        return shared;
    }

    return typeof window === 'undefined' ? '' : window.location.origin;
});

// The page's own address, which og:url keeps even when the canonical points elsewhere.
const pageUrl = computed(() => `${origin.value}${page.url}`);

// The canonical the head names, or the page's own address.
const canonical = computed(() => head.value.canonical ?? pageUrl.value);

const fullTitle = computed(() => (head.value.title ? `${head.value.title} | ${SITE_NAME.value}` : SITE_NAME.value));

// The head's extra meta and link tags as vnodes. Built here rather than with
// v-bind in a v-for, which adds a ref_for prop that Head prints as an attribute.
const extraTags = computed(() => [
    ...head.value.meta.map((tag) => h('meta', {
        'head-key': `meta:${tag.attribute}:${tag.key}`,
        [tag.attribute]: tag.key,
        content: tag.content,
    })),
    // Keyed on type as well, matching Head's dedupe, so two formats at one href both survive.
    ...head.value.links.map((link) => h('link', { 'head-key': `link:${link.rel}:${link.href}:${link.type ?? ''}`, ...link })),
]);
</script>

<template>
    <Head :title="head.title">
        <meta head-key="description" name="description" :content="head.description" />
        <link head-key="canonical" rel="canonical" :href="canonical" />
        <meta v-if="head.noindex" head-key="robots" name="robots" content="noindex, nofollow" />

        <meta head-key="og:type" property="og:type" :content="head.type" />
        <meta head-key="og:site_name" property="og:site_name" :content="SITE_NAME" />
        <meta head-key="og:title" property="og:title" :content="fullTitle" />
        <meta head-key="og:description" property="og:description" :content="head.description" />
        <meta head-key="og:url" property="og:url" :content="pageUrl" />
        <meta v-if="head.image" head-key="og:image" property="og:image" :content="head.image" />

        <meta head-key="twitter:card" name="twitter:card" content="summary_large_image" />
        <meta head-key="twitter:title" name="twitter:title" :content="fullTitle" />
        <meta head-key="twitter:description" name="twitter:description" :content="head.description" />
        <meta v-if="head.image" head-key="twitter:image" name="twitter:image" :content="head.image" />

        <component :is="tag" v-for="tag in extraTags" :key="tag.props['head-key']" />
    </Head>
</template>
