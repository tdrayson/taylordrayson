<script setup>
import { Link } from '@inertiajs/vue3';
import Eyebrow from '../Ui/Eyebrow.vue';
import Icon from '../Ui/Icon.vue';

defineProps({
    // [{ label, href }] — internal links to related pages.
    links: { type: Array, default: () => [] },
});
</script>

<template>
    <section class="mt-14">
        <Eyebrow as="h2" class="text-neutral-900">Further reading</Eyebrow>
        <ul class="mt-4 flex flex-col gap-2.5">
            <li v-for="link in links" :key="link.href">
                <Link
                    :href="link.href"
                    class="group inline-flex items-center gap-2 text-base text-neutral-700 transition-colors hover:text-neutral-900 focus-visible:text-neutral-900 focus-visible:outline-none"
                >
                    <Icon name="ArrowRight01Icon" class="size-4 text-neutral-400 transition-transform group-hover:translate-x-0.5 group-focus-visible:translate-x-0.5" />
                    {{ link.label }}
                </Link>
            </li>
        </ul>
    </section>
</template>
