<script setup>
import { computed, defineAsyncComponent, ref } from 'vue';
import Button from '../Ui/Button.vue';
import MentionList from './MentionList.vue';
import ReactionBar from './ReactionBar.vue';
import ReplyItem from './ReplyItem.vue';

// Loaded on demand, not with the page. Most readers never write anything, and
// a form that is not in the server-rendered HTML is not there to be found by
// something scraping for forms to post at.
const CommentForm = defineAsyncComponent(() => import('./CommentForm.vue'));
const WebmentionForm = defineAsyncComponent(() => import('./WebmentionForm.vue'));

const props = defineProps({
    // One ConversationData: { type, id, url, reactions, replies, mentions }.
    conversation: { type: Object, required: true },
});

const composing = ref(false);
const replyingTo = ref(null);

/**
 * The thread, flattened to one level: a reply to a reply renders beside its
 * siblings rather than stepping further right forever.
 */
const thread = computed(() => props.conversation.replies.map((item) => ({
    ...item,
    nested: item.parentId !== null,
})));

const total = computed(() => props.conversation.replies.length + props.conversation.mentions.length);

function compose(item = null) {
    replyingTo.value = item;
    composing.value = true;
}
</script>

<template>
    <section class="border-t border-neutral-50 pt-8" aria-labelledby="conversation-heading">
        <h2 id="conversation-heading" class="text-label uppercase text-neutral-500">
            {{ total === 1 ? '1 response' : `${total} responses` }}
        </h2>

        <ReactionBar
            class="mt-3"
            :reactions="conversation.reactions"
            :type="conversation.type"
            :id="conversation.id"
        />

        <ol v-if="thread.length" class="mt-8 space-y-6">
            <li v-for="item in thread" :key="item.id">
                <ReplyItem :item="item" :nested="item.nested" @reply="compose" />
            </li>
        </ol>

        <MentionList v-if="conversation.mentions.length" class="mt-8" :mentions="conversation.mentions" />

        <!-- One disclosure for both ways in, so nothing form-shaped is loaded
             or rendered until somebody actually wants to write. -->
        <Button v-if="! composing" class="mt-8" variant="secondary" @click="compose()">
            {{ thread.length ? 'Join in' : 'Be the first to say something' }}
        </Button>

        <div v-else class="mt-8 space-y-6 border-t border-neutral-50 pt-6">
            <h3 class="text-label uppercase text-neutral-500">
                {{ replyingTo ? `Reply to ${replyingTo.authorName}` : 'Leave a comment' }}
            </h3>

            <CommentForm
                :type="conversation.type"
                :id="conversation.id"
                :parent-id="replyingTo?.commentId ?? null"
                :replying-to="replyingTo?.authorName ?? null"
                @cancel="replyingTo = null"
            />

            <div class="border-t border-neutral-50 pt-6">
                <WebmentionForm :target="conversation.url" />
            </div>
        </div>
    </section>
</template>
