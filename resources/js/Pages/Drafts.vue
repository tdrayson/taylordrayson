<script setup>
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Eyebrow from '../Components/Ui/Eyebrow.vue';
import Heading from '../Components/Ui/Heading.vue';
import { relativeDay } from '../lib/format.js';
import { formatDate } from '../lib/dateFormat.js';

defineOptions({ layout: AppLayout, inheritAttrs: false });

defineProps({
    // One group per draftable type, empty groups already dropped server-side.
    groups: { type: Array, default: () => [] },
});

// "Edited" reads better than a bare date against a title you were just working on.
function editedLabel(iso) {
    const relative = relativeDay(iso);

    return relative === null
        ? `Edited ${formatDate(new Date(iso), { weekday: false })}`
        : relative;
}

setLayoutProps({ breadcrumb: [{ label: 'Drafts' }] });
</script>

<template>
    <AppHead :og="{ title: 'Drafts' }" />

    <div class="max-w-2xl">
        <Heading as="h1" size="display">Drafts</Heading>
        <p class="mt-2 text-sm text-neutral-500">
            Drafts. Nobody else can see these.
        </p>

        <p v-if="! groups.length" class="mt-8 text-base text-neutral-700">
            Nothing unfinished. <Link href="/new" class="text-accent-500 underline underline-offset-2 transition-colors hover:text-accent-700">Start something</Link>.
        </p>

        <section v-for="group in groups" :key="group.type" class="mt-8">
            <Eyebrow as="h2" class="text-neutral-500">{{ group.label }} ({{ group.rows.length }})</Eyebrow>

            <ul class="mt-2 divide-y divide-neutral-50 border-y border-neutral-50">
                <li v-for="row in group.rows" :key="row.url">
                    <Link
                        :href="row.url"
                        class="flex items-baseline justify-between gap-4 py-2.5 transition-colors hover:text-accent-500"
                    >
                        <span class="min-w-0 truncate text-base text-neutral-900">{{ row.title }}</span>
                        <span class="shrink-0 text-xs text-neutral-500">{{ row.detail ? `${row.detail}, ` : '' }}{{ editedLabel(row.updated) }}</span>
                    </Link>
                </li>
            </ul>
        </section>
    </div>
</template>
