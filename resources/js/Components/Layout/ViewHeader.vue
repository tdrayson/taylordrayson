<script setup>
import { Link } from '@inertiajs/vue3';
import Eyebrow from '../Ui/Eyebrow.vue';
import Icon from '../Ui/Icon.vue';

defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    eyebrow: { type: String, default: '' },
    prev: { type: Object, default: null },
    next: { type: Object, default: null },
});
</script>

<template>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <Eyebrow v-if="eyebrow" class="text-neutral-500">{{ eyebrow }}</Eyebrow>
            <h1 class="font-display text-5xl font-extrabold tracking-tight" v-html="title" />
            <p v-if="subtitle" class="mt-2 text-sm text-neutral-500">{{ subtitle }}</p>
        </div>
        <div class="flex gap-4 text-sm font-semibold text-neutral-500">
            <Link v-if="prev" :href="prev.href" class="inline-flex items-center gap-1 hover:text-accent-500 focus-visible:text-accent-500">
                <Icon name="ArrowLeft01Icon" class="size-4" /> {{ prev.label }}
            </Link>
            <Link v-if="next" :href="next.href" class="inline-flex items-center gap-1 hover:text-accent-500 focus-visible:text-accent-500">
                {{ next.label }} <Icon name="ArrowRight01Icon" class="size-4" />
            </Link>
        </div>
    </div>
</template>
