<script setup>
import { Link, router } from '@inertiajs/vue3';
import Button from '../Ui/Button.vue';
import Icon from '../Ui/Icon.vue';

defineProps({
    items: { type: Array, default: () => [] },
});

/** Approve and reject go to the moderation route; retry has its own. */
function act(item, action) {
    const url = action === 'retry'
        ? '/hq/failed-jobs/retry'
        : `/moderation/${item.id.split('-')[0]}/${item.id.split('-')[1]}/${action}`;

    router.post(url, {}, { preserveScroll: true });
}

// Only the colours that carry meaning. A held comment and a failed job are two
// different feelings; everything else stays neutral so those two stand out.
const GLYPHS = {
    comment: 'text-accent-500',
    failure: 'text-red-500',
    draft: 'text-neutral-500',
};

// The tinted disc is desktop only. On a phone it costs a third of the title's
// width, and the glyph alone already says which kind of thing this is.
const DISCS = {
    comment: 'sm:bg-accent-50',
    failure: 'sm:bg-neutral-25',
    draft: 'sm:bg-neutral-25',
};
</script>

<template>
    <ul class="divide-y divide-neutral-50 overflow-hidden rounded-lg border border-neutral-50">
        <li v-for="item in items" :key="item.id" class="p-4 sm:p-5">
            <div class="flex gap-3 sm:gap-4">
                <span
                    class="flex size-5 flex-none items-center justify-center sm:size-9 sm:rounded-lg"
                    :class="[GLYPHS[item.kind] ?? GLYPHS.draft, DISCS[item.kind] ?? DISCS.draft]"
                >
                    <Icon :icon="item.icon" class="size-5" />
                </span>

                <div class="min-w-0 flex-1">
                    <!-- Column-reverse on a phone lifts the age above the title,
                         so the title gets the full width instead of wrapping
                         three times around a right-hand timestamp. -->
                    <div class="flex flex-col-reverse gap-x-4 sm:flex-row sm:items-start sm:justify-between">
                        <Link
                            :href="item.href"
                            class="min-w-0 text-base font-semibold text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none"
                        >
                            {{ item.title }}
                        </Link>
                        <span
                            class="shrink-0 text-2xs font-semibold uppercase tracking-wider text-neutral-500 sm:whitespace-nowrap sm:text-xs sm:font-normal sm:normal-case sm:tracking-normal"
                        >{{ item.age }}</span>
                    </div>

                    <p v-if="item.detail" class="mt-0.5 text-sm text-neutral-500">{{ item.detail }}</p>

                    <!-- Quoted so a held comment can be read and judged here,
                         rather than opening a second page to find out what it says. -->
                    <p
                        v-if="item.body"
                        class="mt-3 border-l-2 border-neutral-100 pl-3 text-sm text-neutral-700"
                    >
                        {{ item.body }}
                    </p>

                    <div v-if="item.actions.length" class="mt-3 flex flex-wrap gap-2">
                        <Button
                            v-for="action in item.actions"
                            :key="action.action"
                            size="sm"
                            :variant="action.variant"
                            @click="act(item, action.action)"
                        >
                            {{ action.label }}
                        </Button>
                    </div>
                </div>
            </div>
        </li>
    </ul>
</template>
