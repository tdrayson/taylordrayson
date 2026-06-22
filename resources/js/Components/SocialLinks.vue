<script setup>
import { GithubIcon, NewTwitterIcon, RssIcon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

defineProps({
    links: {
        type: Array,
        default: () => [
            { icon: GithubIcon, href: '#', label: 'GitHub', rel: 'me' },
            { icon: NewTwitterIcon, href: '#', label: 'X', rel: 'me' },
            { icon: RssIcon, href: '#', label: 'RSS feed', rel: 'alternate' },
        ],
    },
});
</script>

<template>
    <div class="flex gap-4">
        <a
            v-for="link in links"
            :key="link.label"
            :href="link.href"
            :rel="link.rel"
            :aria-label="link.label"
            class="text-ink-3 transition-colors hover:text-accent"
        >
            <Icon :icon="link.icon" class="size-4" />
        </a>
    </div>
</template>
