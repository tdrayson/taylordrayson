<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    icon: { type: [Array, Object], required: true },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        :aria-current="active ? 'page' : undefined"
        class="flex items-center gap-3 rounded-md px-3 py-2.5 text-base font-medium transition-colors md:py-2 md:text-sm"
        :class="active ? 'bg-surface font-semibold text-ink' : 'text-ink-2 hover:bg-surface'"
    >
        <Icon :icon="icon" class="size-5 flex-none" :class="active ? 'text-accent' : 'text-ink-3'" />
        {{ label }}
    </Link>
</template>
