<script setup>
import { GithubIcon, NewTwitterIcon, RssIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    links: {
        type: Array,
        default: () => [
            { icon: GithubIcon, href: 'https://github.com/tdrayson', label: 'GitHub', rel: 'me' },
            { icon: NewTwitterIcon, href: '#', label: 'X', rel: 'me' },
            { icon: RssIcon, href: '/feeds', label: 'Feeds', rel: null },
        ],
    },
});

/**
 * rel="me" profiles that point somewhere real are also h-card URLs, so they
 * consolidate identity for microformats parsers. Placeholders are skipped.
 *
 * @param {{ rel?: string, href: string }} link
 * @return {boolean}
 */
function isIdentityUrl(link) {
    return link.rel === 'me' && link.href !== '#';
}

/**
 * Absolute external links open in a new tab; internal paths and placeholders do not.
 *
 * @param {{ href: string }} link
 * @return {boolean}
 */
function isExternal(link) {
    return link.href.startsWith('http');
}

/**
 * @param {{ rel?: string, href: string }} link
 * @return {string|undefined}
 */
function relFor(link) {
    if (!isExternal(link)) {
        return link.rel ?? undefined;
    }
    return [link.rel, 'noopener', 'noreferrer'].filter(Boolean).join(' ');
}

/**
 * @param {{ href: string, label: string }} link
 * @return {string}
 */
function ariaFor(link) {
    return isExternal(link) ? `${link.label}, opens in a new tab` : link.label;
}
</script>

<template>
    <div class="flex gap-4">
        <a
            v-for="link in links"
            :key="link.label"
            :href="link.href"
            :target="isExternal(link) ? '_blank' : undefined"
            :rel="relFor(link)"
            :aria-label="ariaFor(link)"
            :class="['text-neutral-500 transition-colors hover:text-accent-500 focus-visible:text-accent-500', { 'u-url': isIdentityUrl(link) }]"
        >
            <Icon :icon="link.icon" class="size-5" />
        </a>
    </div>
</template>
