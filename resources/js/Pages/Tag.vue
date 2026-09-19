<script setup>
import { setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import DateGroup from '../Components/Timeline/DateGroup.vue';
import AuthorRef from '../Components/Profile/AuthorRef.vue';
import Heading from '../Components/Ui/Heading.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    og: { type: Object, default: () => ({}) },
    name: { type: String, required: true },
    groups: { type: Array, default: () => [] },
});

setLayoutProps({
    breadcrumb: [{ label: 'Tags', href: '/tags' }, { label: props.name }],
});
</script>

<template>
    <AppHead :og="og" />

    <header>
        <Heading as="h1" size="display">Tagged {{ name }}</Heading>
    </header>

    <div v-if="groups.length" class="h-feed mt-10 flex flex-col gap-14">
        <AuthorRef />
        <DateGroup
            v-for="group in groups"
            :key="group.label"
            :label="group.label"
            :date="group.date"
            :href="group.href"
            :items="group.items"
        />
    </div>

    <p v-else class="mt-10 text-sm text-neutral-500">Nothing here yet.</p>
</template>
