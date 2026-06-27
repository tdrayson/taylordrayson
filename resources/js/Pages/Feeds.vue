<script setup>
import { ref, computed, onMounted } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import { RssIcon, SourceCodeIcon } from '@hugeicons-pro/core-stroke-rounded';
import AppLayout from '../Layouts/AppLayout.vue';
import Icon from '../Components/Ui/Icon.vue';
import FeedPresetCard from '../Components/Feeds/FeedPresetCard.vue';
import FeedTypeToggle from '../Components/Feeds/FeedTypeToggle.vue';
import FeedUrlField from '../Components/Feeds/FeedUrlField.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    types: { type: Array, default: () => [] },
    presets: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Feeds' }],
});

const allKeys = computed(() => props.types.map((type) => type.key));

// Selection is opt-in: nothing is chosen until the visitor picks a preset or
// toggles a type. `activePreset` tracks which preset (if any) the current
// selection came from; toggling any single type drops it to a custom mix (null)
// so the URL switches from ?filter= to ?types=.
const selected = ref(new Set());
const activePreset = ref(null);

function selectPreset(preset) {
    selected.value = new Set(preset.types);
    activePreset.value = preset.key;
}

function toggleType(key) {
    const next = new Set(selected.value);
    if (next.has(key)) {
        next.delete(key);
    } else {
        next.add(key);
    }
    selected.value = next;
    activePreset.value = null;
}

const isSelected = (key) => selected.value.has(key);

// Selected keys in registry order, so the ?types= URL is stable and readable.
const selectedKeys = computed(() => allKeys.value.filter((key) => selected.value.has(key)));

const origin = ref('');
onMounted(() => (origin.value = window.location.origin));

// Everything → bare URL; a named preset → ?filter=; anything else → ?types=.
const querySuffix = computed(() => {
    if (activePreset.value === 'everything') {
        return '';
    }
    if (activePreset.value) {
        return `?filter=${activePreset.value}`;
    }
    return selectedKeys.value.length ? `?types=${selectedKeys.value.join(',')}` : null;
});

const hasFeed = computed(() => querySuffix.value !== null);

const rssUrl = computed(() => `${origin.value}/feed/rss${querySuffix.value ?? ''}`);
const jsonUrl = computed(() => `${origin.value}/feed/json${querySuffix.value ?? ''}`);
</script>

<template>
    <div class="mx-auto max-w-2xl">
        <AppHead :og="og" />

        <header class="mb-10 flex items-start gap-4">
            <span class="hidden size-12 shrink-0 items-center justify-center rounded-full bg-neutral-25 text-accent-500 sm:flex">
                <Icon :icon="RssIcon" class="size-6" />
            </span>
            <div class="min-w-0">
                <h1 class="font-display text-display">Feeds</h1>
                <p class="mt-2 max-w-prose text-meta text-neutral-500">
                    Pop me in your feed reader. Grab one of my ready-made mixes, or flip the switches below
                    and build a feed of exactly the bits you care about.
                </p>
            </div>
        </header>

        <section class="mb-10">
            <h2 class="mb-3 text-label uppercase text-neutral-500">Ready-made mixes</h2>
            <div class="grid gap-3 sm:grid-cols-2">
                <FeedPresetCard
                    v-for="preset in presets"
                    :key="preset.key"
                    :label="preset.label"
                    :description="preset.description"
                    :recommended="preset.key === 'curated'"
                    :active="activePreset === preset.key"
                    @select="selectPreset(preset)"
                />
            </div>
        </section>

        <section class="mb-10">
            <h2 class="mb-1 text-label uppercase text-neutral-500">Build your own</h2>
            <p class="mb-4 text-meta text-neutral-500">Flip on whatever you fancy. Mix and match to taste.</p>
            <div class="flex flex-wrap gap-2">
                <FeedTypeToggle
                    v-for="type in types"
                    :key="type.key"
                    :type-key="type.key"
                    :label="type.label"
                    :active="isSelected(type.key)"
                    @toggle="toggleType"
                />
            </div>
        </section>

        <section>
            <h2 class="mb-4 text-label uppercase text-neutral-500">Your feed URLs</h2>
            <div v-if="hasFeed" class="space-y-5">
                <FeedUrlField label="RSS Feed" :icon="RssIcon" :url="rssUrl" />
                <FeedUrlField label="JSON Feed" :icon="SourceCodeIcon" :url="jsonUrl" />
                <p class="text-caption text-neutral-500">
                    Team Atom? Swap <code class="text-neutral-700">/feed/rss</code> for
                    <code class="text-neutral-700">/feed/atom</code> and you're sorted.
                </p>
            </div>
            <p v-else class="text-meta text-neutral-500">Flip at least one switch and your feed will appear here.</p>
        </section>
    </div>
</template>
