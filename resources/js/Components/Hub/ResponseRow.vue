<script setup>
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

defineProps({
    item: { type: Object, required: true },
});
</script>

<template>
    <li>
        <!-- The row is the link, not the title inside it: the whole thing
             already lights up on hover, so anything less is a target the
             highlight lied about. -->
        <Link
            :href="item.entry.href"
            class="group flex gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
        >
            <Icon :icon="item.icon" class="mt-0.5 size-4 flex-none text-neutral-400" />

            <!-- The age drops below the sentence on a phone; beside it there is
                 no room for both, and the wrap lands mid-title. -->
            <span class="min-w-0 flex-1 sm:flex sm:items-start sm:gap-4">
                <span class="block min-w-0 sm:flex-1">
                    <span class="block text-sm text-neutral-700">
                        {{ item.sentence }}
                        <span class="font-semibold text-neutral-900 transition-colors group-hover:text-accent-500 group-focus-visible:text-accent-500">{{ item.entry.title }}</span><span v-if="item.held" class="text-neutral-500">, waiting on you</span>
                    </span>

                    <span v-if="item.body" class="mt-1 block truncate text-sm text-neutral-500">{{ item.body }}</span>
                </span>

                <span class="mt-0.5 block text-xs text-neutral-400 sm:mt-0 sm:shrink-0 sm:whitespace-nowrap">{{ item.age }}</span>
            </span>
        </Link>
    </li>
</template>
