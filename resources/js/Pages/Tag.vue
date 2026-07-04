<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    name: { type: String, required: true },
    groups: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: `Tagged ${props.name}` }],
});
</script>

<template>
    <AppHead :og="og" />

    <header>
        <p class="text-eyebrow uppercase text-neutral-500">Tag</p>
        <h1 class="mt-1 font-display text-display">Tagged {{ name }}</h1>
    </header>

    <div v-if="groups.length" class="mt-10 flex flex-col gap-14">
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
        />
    </div>

    <p v-else class="mt-10 text-meta text-neutral-500">Nothing here yet.</p>
</template>
