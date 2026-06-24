<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Home01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

// Every crumb except the current page. The current page is the up-navigation's
// least useful crumb (the page H1 already shows it), so on mobile we lead with
// the ancestors and drop the current label.
const ancestors = computed(() => props.items.slice(0, -1));
const current = computed(() => props.items[props.items.length - 1] ?? null);
</script>

<template>
    <nav v-if="items.length" aria-label="Breadcrumb" class="flex min-w-0 items-center text-sm text-ink-3">
        <Link href="/" aria-label="Home" class="flex shrink-0 items-center transition-colors hover:text-accent">
            <Icon :icon="Home01Icon" class="size-4" />
        </Link>

        <!-- Ancestors: shown inline on every breakpoint (these are the real navigation). -->
        <template v-for="(item, index) in ancestors" :key="index">
            <span class="mx-2 shrink-0 text-line" aria-hidden="true">/</span>
            <Link v-if="item.href" :href="item.href" class="shrink-0 transition-colors hover:text-accent">{{ item.label }}</Link>
            <span v-else class="shrink-0">{{ item.label }}</span>
        </template>

        <!-- Current page: truncates on desktop; hidden on mobile when there are
             ancestors, since the page heading already states where you are. -->
        <div
            v-if="current"
            class="min-w-0 items-center"
            :class="ancestors.length ? 'hidden md:flex' : 'flex'"
        >
            <span class="mx-2 shrink-0 text-line" aria-hidden="true">/</span>
            <span class="min-w-0 truncate" aria-current="page">{{ current.label }}</span>
        </div>
    </nav>
</template>
