<script setup>
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import Accordion from '../Ui/Accordion.vue';
import Facepile from './Facepile.vue';
import MentionList from './MentionList.vue';
import ReactionBar from './ReactionBar.vue';
import ReplyItem from './ReplyItem.vue';

// Loaded on demand, and separately: someone sending a webmention never fetches
// the comment form, and a page nobody responds to fetches neither.
const CommentForm = defineAsyncComponent(() => import('./CommentForm.vue'));
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

const props = defineProps({
    // One ConversationData: { type, id, url, reactions, faces, replies, mentions }.
    conversation: { type: Object, required: true },
});

const replyingTo = ref(null);
const commentsOpen = ref(false);

/**
 * The thread, flattened to one level: a reply to a reply renders beside its
 * siblings rather than stepping further right forever.
 */
const thread = computed(() => props.conversation.replies.map((item) => ({
    ...item,
    nested: item.parentId !== null,
})));

// A verb for the buttons ("React") and a noun for the pile ("4 reactions"),
// which is enough to tell an invitation from a tally without saying
// "elsewhere" twice over, since the mentions block below already does.
const faceLabel = computed(() => (props.conversation.faces.length === 1
    ? '1 reaction'
    : `${props.conversation.faces.length} reactions`));

const replyLabel = computed(() => (thread.value.length === 1 ? '1 reply' : `${thread.value.length} replies`));

/** Open the comment form against a comment, and take the reader to it. */
async function reply(item) {
    replyingTo.value = item;
    commentsOpen.value = true;

    await nextTick();
    document.getElementById('leave-a-comment')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <!-- One rule, at the top. Everything below is separated by space and the
         weight of the headings rather than by more lines. -->
    <section class="border-t border-neutral-50 pt-10" aria-labelledby="conversation-heading">
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

            <div v-if="conversation.faces.length">
                <h3 class="text-label uppercase text-neutral-500">{{ faceLabel }}</h3>
                <Facepile class="mt-3" :faces="conversation.faces" />
            </div>

            <div v-if="thread.length">
                <h3 class="text-label uppercase text-neutral-500">{{ replyLabel }}</h3>

                <ol class="mt-4 space-y-6">
                    <li v-for="item in thread" :key="item.id">
                        <ReplyItem :item="item" :nested="item.nested" @reply="reply" />
                    </li>
                </ol>
            </div>

            <MentionList v-if="conversation.mentions.length" :mentions="conversation.mentions" />
        </div>

        <!-- Two toggles rather than one: they are different things to write,
             and each pulls down only its own form. -->
        <div class="mt-10">
            <Accordion
                id="leave-a-comment"
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

            <Accordion title="Written a response of your own?" :bordered="false">
                <template #default="{ expanded }">
                    <WebmentionForm v-if="expanded" :target="conversation.url" />
                </template>
            </Accordion>
        </div>
    </section>
</template>
