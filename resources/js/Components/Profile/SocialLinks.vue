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
</script>

<template>
    <div class="flex gap-4">
        <a
            v-for="link in links"
            :key="link.label"
            :href="link.href"
            :rel="link.rel"
            :aria-label="link.label"
            :class="['text-neutral-500 transition-colors hover:text-accent-500', { 'u-url': isIdentityUrl(link) }]"
        >
            <Icon :icon="link.icon" class="size-4" />
        </a>
    </div>
</template>
