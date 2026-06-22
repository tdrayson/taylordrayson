<script setup>
import { Link } from '@inertiajs/vue3';
import { Home01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

defineProps({
    items: { type: Array, default: () => [] },
});
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="flex items-center text-sm text-ink-3">
        <Link href="/" aria-label="Home" class="flex items-center transition-colors hover:text-accent">
            <Icon :icon="Home01Icon" class="size-4" />
        </Link>
        <template v-for="(item, index) in items" :key="index">
            <span class="mx-2 text-line" aria-hidden="true">/</span>
            <Link v-if="item.href" :href="item.href" class="transition-colors hover:text-accent">{{ item.label }}</Link>
            <span v-else :aria-current="index === items.length - 1 ? 'page' : undefined">{{ item.label }}</span>
        </template>
    </nav>
</template>
