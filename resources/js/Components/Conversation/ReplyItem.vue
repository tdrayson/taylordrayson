<script setup>
import { computed } from 'vue';
import Avatar from './Avatar.vue';
import ExternalLink from '../Ui/ExternalLink.vue';

const props = defineProps({
    // One ConversationItem: { id, kind, authorName, authorUrl, authorPhoto,
    // body, occurredAt: { time, label, offset, iso }, parentId, sourceUrl }.
    item: { type: Object, required: true },
    // Rendered as a reply to somebody, one level deep only.
    nested: { type: Boolean, default: false },
});

defineEmits(['reply']);

// An RSVP carries no prose worth threading, so its kind is what it said.
const label = computed(() => (props.item.kind === 'rsvp' ? 'RSVP’d' : null));
</script>

<template>
    <article
        :id="item.id"
        class="h-cite flex gap-3"
        :class="nested && 'ml-6 border-l border-neutral-50 pl-4 sm:ml-11'"
    >
        <Avatar class="mt-0.5" :name="item.authorName" :photo="item.authorPhoto" />

        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-baseline gap-x-2 text-meta">
                <!-- Same tab: an author's own site is a normal onward link, not
                     an aside, so it needs no new-tab announcement. -->
                <a
                    v-if="item.authorUrl"
                    :href="item.authorUrl"
                    rel="noopener noreferrer nofollow"
                    class="p-author h-card rounded-sm font-semibold text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >{{ item.authorName }}</a>
                <span v-else class="p-author font-semibold text-neutral-900">{{ item.authorName }}</span>

                <time
                    class="dt-published text-caption text-neutral-500"
                    :datetime="item.occurredAt.iso"
                    :title="`${item.occurredAt.label} (UTC${item.occurredAt.offset})`"
                >{{ item.occurredAt.label }}</time>
            </p>

            <p v-if="label" class="mt-1 text-meta text-neutral-700">{{ label }}</p>

            <p v-if="item.body" class="e-content mt-1 whitespace-pre-line text-body text-neutral-900">{{ item.body }}</p>

            <p v-if="item.sourceUrl" class="mt-1">
                <ExternalLink :href="item.sourceUrl" label="Read it where it was written" class="u-url" />
            </p>

            <!-- Inside the content column rather than the row, so it lines up
                 with the comment it answers at either indent. -->
            <button
                v-if="item.commentId"
                type="button"
                class="mt-1 rounded-sm text-caption text-neutral-500 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                @click="$emit('reply', item)"
            >
                Reply
            </button>
        </div>
    </article>
</template>
