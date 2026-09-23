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
 *
 * Replies only here: a row of likes is collapsed across everyone who left one
 * that day, so there is no single response for the mark to land on.
 */
const markable = computed(() => (props.item.kind === 'reply' ? markableId(props.item) : null));
</script>

<template>
    <li data-response-row class="group/row relative">
        <!-- The row is the link, not the title inside it: the whole thing
             already lights up on hover, so anything less is a target the
             highlight lied about. On a touch screen it also keeps clear of
             the always-visible mark button beside it. -->
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
                        <span class="font-semibold text-neutral-900 transition-colors group-hover:text-accent-500 group-focus-visible:text-accent-500">{{ item.entry.title }}</span>
                    </span>

                    <span v-if="item.body" class="mt-1 block truncate text-sm text-neutral-500">{{ item.body }}</span>
                </span>

                <span class="mt-0.5 block text-xs text-neutral-400 sm:mt-0 sm:shrink-0 sm:whitespace-nowrap">{{ item.age }}</span>
            </span>
        </Link>

        <!-- Sits over the row's own glyph, which is where the entry page puts
             it over the avatar. A sibling of the link rather than inside it: a
             button nested in a link is invalid, and a click on it would also
             follow the link. -->
        <button
            v-if="markable"
            type="button"
            class="absolute left-2 top-2 flex size-6 items-center justify-center rounded-full bg-accent-50 text-accent-700 opacity-0 transition hover:bg-accent-100 focus-visible:opacity-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 group-hover/row:opacity-100 pointer-coarse:opacity-100"
            aria-label="Mark as mine"
            @click="setMine(markable, true)"
        >
            <Icon name="UserIcon" class="size-3.5" />
        </button>
    </li>
</template>
