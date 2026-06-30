<script setup>
import { Link } from '@inertiajs/vue3';
import { ArrowRight01Icon } from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../Ui/Icon.vue';

defineProps({
    // [{ label, href }] — internal links to related pages.
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="mt-14">
        <h2 class="text-label uppercase tracking-wider text-neutral-900">Further reading</h2>
        <ul class="mt-4 flex flex-col gap-2.5">
            <li v-for="link in links" :key="link.href">
                <Link
                    :href="link.href"
                    class="group inline-flex items-center gap-2 text-body text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none"
                >
                    <Icon :icon="ArrowRight01Icon" class="size-4 text-neutral-400 transition-transform group-hover:translate-x-0.5 group-focus-visible:translate-x-0.5" />
                    {{ link.label }}
                </Link>
            </li>
        </ul>
    </section>
</template>
