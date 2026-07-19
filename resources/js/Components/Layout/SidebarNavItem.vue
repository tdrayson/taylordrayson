<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

defineProps({
    href: { type: String, required: true },
    label: { type: String, required: true },
    // Optional: child links inside a collapsed group render text-only.
    // String values are icon-registry names (e.g. from entryTypes / navigation).
    icon: { type: [Array, Object, String], default: null },
    active: { type: Boolean, default: false },
});
</script>

<template>
    <Link
        :href="href"
        :aria-current="active ? 'page' : undefined"
        class="-ml-3 flex items-center gap-3 rounded-md px-3 py-2.5 text-base font-medium transition-colors md:py-2 md:text-sm"
        :class="active ? 'bg-neutral-25 font-semibold text-neutral-900' : 'text-neutral-700 hover:bg-neutral-25'"
    >
        <Icon v-if="icon" :icon="icon" class="size-5 flex-none" :class="active ? 'text-accent-500' : 'text-neutral-500'" />
        {{ label }}
    </Link>
</template>
