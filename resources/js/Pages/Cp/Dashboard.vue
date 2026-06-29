<script setup>
import { Link, setLayoutProps } from '@inertiajs/vue3';
import AppLayout from '../../Layouts/AppLayout.vue';
import AppHead from '../../Components/AppHead.vue';
import Card from '../../Components/Ui/Card.vue';

defineOptions({ layout: AppLayout });

defineProps({
    collections: { type: Array, default: () => [] },
});

setLayoutProps({ mode: 'cp' });
</script>

<template>
    <AppHead :og="{ title: 'Control panel' }" />
    <h1 class="font-display text-display">Control panel</h1>

    <div v-for="group in collections" :key="group.group" class="mt-8">
        <h2 class="text-eyebrow uppercase text-neutral-500">{{ group.group }}</h2>
        <div class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-3">
            <Link v-for="item in group.items" :key="item.slug" :href="`/cp/${item.slug}`">
                <Card variant="outline" class="transition-colors hover:border-accent-500">
                    <p class="text-stat font-display text-neutral-900">{{ item.count }}</p>
                    <p class="text-meta text-neutral-700">{{ item.label }}</p>
                </Card>
            </Link>
        </div>
    </div>
</template>
