<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';
import { markableId, setMine } from '../../lib/mineMark.js';

const props = defineProps({
    item: { type: Object, required: true },
});

/**
 * The id to mark this reply as mine with. Once marked it leaves the list on
 * reload, since my own reply is not somebody responding to me.
 */
const markable = computed(() => markableId(props.item));
</script>

<template>
    <li class="group/row relative">
        <!-- The row is the link, not the title inside it: the whole thing
             already lights up on hover, so anything less is a target the
             highlight lied about. On a touch screen it also keeps clear of
             the always-visible mark button beside it. -->
        <Link
            :href="item.entry.href"
            class="group flex gap-3 rounded-lg px-3 py-2.5 transition-colors hover:bg-neutral-25 focus-visible:bg-neutral-25 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            :class="markable && 'pointer-coarse:pr-28'"
        >
            <Icon :icon="item.icon" class="mt-0.5 size-4 flex-none text-neutral-400" />

            <!-- The age drops below the sentence on a phone; beside it there is
                 no room for both, and the wrap lands mid-title. -->
            <span class="min-w-0 flex-1 sm:flex sm:items-start sm:gap-4">
                <span class="block min-w-0 sm:flex-1">
                    <span class="block text-sm text-neutral-700">
                        {{ item.sentence }}
                        <span class="font-semibold text-neutral-900 transition-colors group-hover:text-accent-500 group-focus-visible:text-accent-500">{{ item.entry.title }}</span>
                    </span>

                    <span v-if="item.body" class="mt-1 block truncate text-sm text-neutral-500">{{ item.body }}</span>
                </span>

                <!-- Gives way to the mark button on hover, which takes its place. -->
                <span
                    class="mt-0.5 block text-xs text-neutral-400 transition-opacity sm:mt-0 sm:shrink-0 sm:whitespace-nowrap"
                    :class="markable && 'sm:group-hover/row:opacity-0'"
                >{{ item.age }}</span>
            </span>
        </Link>

        <!-- A sibling of the link rather than inside it: a button nested in a
             link is invalid, and a click on it would also follow the link. -->
        <button
            v-if="markable"
            type="button"
            class="absolute right-3 top-2.5 rounded-sm bg-neutral-25 px-1 text-xs text-neutral-500 opacity-0 transition hover:text-accent-500 focus-visible:text-accent-500 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 group-hover/row:opacity-100 pointer-coarse:bg-transparent pointer-coarse:opacity-100"
            @click="setMine(markable, true)"
        >
            Mark as mine
        </button>
    </li>
</template>
