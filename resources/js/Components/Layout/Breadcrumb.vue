<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const page = usePage();

// On the home page the icon-only link would read as bare, so label it "Home"
// and make it static (you're already here). Elsewhere it's an icon link home.
const isHome = computed(() => page.url === '/');

// Every crumb except the current page. The current page is the up-navigation's
// least useful crumb (the page H1 already shows it), so on mobile we lead with
// the ancestors and drop the current label.
const ancestors = computed(() => props.items.slice(0, -1));
const current = computed(() => props.items[props.items.length - 1] ?? null);
</script>

<template>
    <nav aria-label="Breadcrumb" class="flex min-w-0 items-center text-sm text-neutral-500">
        <span v-if="isHome" class="flex shrink-0 items-center gap-2 text-neutral-700" aria-current="page">
            <Icon name="Home03Icon" class="size-4" />
            Home
        </span>
        <Link v-else href="/" aria-label="Home" class="flex shrink-0 items-center transition-colors hover:text-accent-500 focus-visible:text-accent-500">
            <Icon name="Home03Icon" class="size-4" />
        </Link>

        <template v-for="(item, index) in ancestors" :key="index">
            <span class="mx-2 shrink-0 text-neutral-100" aria-hidden="true">/</span>
            <Link v-if="item.href" :href="item.href" class="shrink-0 transition-colors hover:text-accent-500 focus-visible:text-accent-500">{{ item.label }}</Link>
            <span v-else class="shrink-0">{{ item.label }}</span>
        </template>

        <!-- Current page: truncates on desktop; hidden on mobile when there are
             ancestors, since the page heading already states where you are. -->
        <div
            v-if="current"
            class="min-w-0 items-center"
            :class="ancestors.length ? 'hidden md:flex' : 'flex'"
        >
            <span class="mx-2 shrink-0 text-neutral-100" aria-hidden="true">/</span>
            <!-- aria-label gives a short crumb ("29") a self-describing name ("29 June 2026"). -->
            <span class="min-w-0 truncate" aria-current="page" :aria-label="current.ariaLabel || undefined">{{ current.label }}</span>
        </div>
    </nav>
</template>
