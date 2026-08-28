<script setup>
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import Accordion from '../Ui/Accordion.vue';
import ReactionBar from './ReactionBar.vue';
import ResponseItem from './ResponseItem.vue';

// Loaded on demand: most readers never write anything, and a form that is not
// in the server-rendered HTML is not there to be found by something scraping
// for forms to post at.
const CommentForm = defineAsyncComponent(() => import('./CommentForm.vue'));

const props = defineProps({
    // One ConversationData: { type, id, url, reactions, responses }.
    conversation: { type: Object, required: true },
});

const replyingTo = ref(null);
const commentsOpen = ref(false);

/**
 * The thread, flattened to one level: a reply to a reply renders beside its
 * siblings rather than stepping further right forever.
 */
const thread = computed(() => props.conversation.responses.map((item) => ({
    ...item,
    nested: item.parentId !== null,
})));

const heading = computed(() => (thread.value.length === 1 ? '1 response' : `${thread.value.length} responses`));

/** Open the comment form against a comment, and take the reader to it. */
async function reply(item) {
    replyingTo.value = item;
    commentsOpen.value = true;

    await nextTick();
    document.getElementById('leave-a-comment')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <!-- No rule of its own: EntryFooter already draws one above, and a second
         one a few lines below it reads as a mistake. -->
    <section aria-labelledby="conversation-heading">
        <h2 id="conversation-heading" class="sr-only">Responses</h2>

        <div class="space-y-10">
            <div>
                <h3 class="text-label uppercase text-neutral-500">React</h3>
                <ReactionBar
                    class="mt-3"
                    :reactions="conversation.reactions"
                    :type="conversation.type"
                    :id="conversation.id"
                />
            </div>

            <div v-if="thread.length">
                <h3 class="text-label uppercase text-neutral-500">{{ heading }}</h3>

                <!-- One stream, every kind. A gesture renders as a single line
                     and a written response as a block, so the weight difference
                     comes from the content rather than from separate lists. -->
                <ol class="mt-4">
                    <li
                        v-for="(item, index) in thread"
                        :key="item.id"
                        :class="index === 0 ? '' : (item.body ? 'mt-5' : 'mt-2')"
                    >
                        <ResponseItem :item="item" :nested="item.nested" @reply="reply" />
                    </li>
                </ol>
            </div>
        </div>

        <Accordion
            id="leave-a-comment"
            class="mt-10 max-w-md"
            :title="replyingTo ? `Reply to ${replyingTo.authorName}` : 'Leave a comment'"
            :open="commentsOpen"
            :bordered="false"
        >
            <template #default="{ expanded }">
                <CommentForm
                    v-if="expanded"
                    :type="conversation.type"
                    :id="conversation.id"
                    :parent-id="replyingTo?.commentId ?? null"
                    :replying-to="replyingTo?.authorName ?? null"
                    @cancel="replyingTo = null"
                />
            </template>
        </Accordion>
    </section>
</template>
