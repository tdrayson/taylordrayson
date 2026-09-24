<script setup>
import { computed } from 'vue';
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import AttentionList from '../Components/Hub/AttentionList.vue';
import EntryCounts from '../Components/Hub/EntryCounts.vue';
import Eyebrow from '../Components/Ui/Eyebrow.vue';
import Heading from '../Components/Ui/Heading.vue';
import Icon from '../Components/Ui/Icon.vue';
import ResponseStream from '../Components/Hub/ResponseStream.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // Things only you can resolve. Empty is the state worth designing for.
    attention: { type: Array, default: () => [] },
    // The latest few only, newest first, likes on one entry already collapsed.
    responses: { type: Array, default: () => [] },
    // Every data type, its count, and when it last got an entry.
    entries: { type: Array, default: () => [] },
});

const unread = computed(() => props.responses.filter((item) => item.isNew).length);

setLayoutProps({ breadcrumb: [{ label: 'HQ' }] });
</script>

<template>
    <AppHead :og="{ title: 'HQ' }" />

    <div class="max-w-2xl">
        <Heading as="h1" size="display">HQ</Heading>

        <section class="mt-10">
            <Eyebrow as="h2" class="text-neutral-500">
                Needs you<template v-if="attention.length"> ({{ attention.length }})</template>
            </Eyebrow>

            <div
                v-if="! attention.length"
                class="mt-3 flex items-center gap-3 rounded-lg bg-neutral-25 p-5"
            >
                <Icon name="CheckmarkCircle02Icon" class="size-6 flex-none text-green-600" />
                <p class="text-base text-neutral-700">Nothing needs you.</p>
            </div>

            <AttentionList v-else :items="attention" class="mt-3" />
        </section>

        <section class="mt-12">
            <div class="flex items-baseline justify-between gap-4">
                <Eyebrow as="h2" class="text-neutral-500">Responses</Eyebrow>
                <span v-if="unread" class="text-xs text-neutral-500">{{ unread }} new</span>
            </div>

            <p v-if="! responses.length" class="mt-3 text-sm text-neutral-500">
                Nobody has said anything yet.
            </p>

            <!-- Bleeds a step past the column on both sides, so the row's own
                 padding puts its text back in line with the heading and the
                 count above it, and only the hover reaches outside. -->
            <ResponseStream v-else :items="responses" class="mt-3 -ml-3 w-bleed-6" />
        </section>

        <section class="mt-12">
            <Eyebrow as="h2" class="text-neutral-500">Entries</Eyebrow>
            <EntryCounts :types="entries" class="mt-3" />
        </section>
    </div>
</template>
