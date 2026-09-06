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
 * The thread: newest conversation first, but each reply kept under the response
 * it answers. Sorting the whole list by date alone put a reply above its own
 * parent, which reads as a non-sequitur.
 *
 * Flattened to one level, so a reply to a reply sits beside its siblings rather
 * than stepping further right forever. Within a thread the replies run oldest
 * first, because a conversation reads forwards even when the list does not.
 */
const thread = computed(() => {
    const all = props.conversation.responses;
    const children = new Map();

    for (const item of all) {
        if (item.parentId === null) {
            continue;
        }

        const siblings = children.get(item.parentId) ?? [];
        siblings.push(item);
        children.set(item.parentId, siblings);
    }

    const at = (item) => new Date(item.occurredAt.iso).getTime();
    const byOldest = (a, b) => at(a) - at(b);

    // A thread is ordered by its latest activity, not by when it started: a
    // reply today to a comment from last month makes that conversation the
    // newest thing here, and sorting on the parent alone would bury it where
    // nobody looks. The dates on screen explain the order without a label.
    const lastActivity = (parent) => Math.max(
        at(parent),
        ...(children.get(parent.commentId) ?? []).map(at),
    );

    return all
        .filter((item) => item.parentId === null)
        .sort((a, b) => lastActivity(b) - lastActivity(a))
        .flatMap((parent) => [
            { ...parent, nested: false },
            ...(children.get(parent.commentId) ?? []).sort(byOldest).map((child) => ({ ...child, nested: true })),
        ]);
});

const heading = computed(() => (thread.value.length === 1 ? '1 response' : `${thread.value.length} responses`));

// Derived from the responses rather than sent separately, so the summary line
// can never disagree with the thread it summarises.
// A like is the binary gesture: a webmention like-of today, a kudo or a Swarm
// like later. Reacji are counted by the bar itself from its own buckets.
const likeCount = computed(() => thread.value.filter((item) => item.kind === 'like').length);
// Anything that carried something written, whoever wrote it and wherever from.
const replyCount = computed(() => thread.value.filter((item) => item.body).length);

/** Open the comment form against a comment, and take the reader to it. */
async function reply(item) {
    replyingTo.value = item;
    commentsOpen.value = true;

    await nextTick();
    document.getElementById('leave-a-comment')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <!-- No rules anywhere in here. Separation is space and the weight of the
         headings, which is what stops a short entry looking like a form. -->
    <section aria-labelledby="conversation-heading">

        <div class="space-y-10">
            <!-- A summary line, not a labelled section: the counts read as part
                 of the entry rather than as a form to fill in. -->
            <ReactionBar
                :reactions="conversation.reactions"
                :like-count="likeCount"
                :reply-count="replyCount"
                :type="conversation.type"
                :id="conversation.id"
            />

            <div v-if="thread.length">
                <h2 id="conversation-heading" class="font-display text-section text-neutral-900">{{ heading }}</h2>

                <!-- One stream, every kind. A gesture renders as a single line
                     and a written response as a block, so the weight difference
                     comes from the content rather than from separate lists. -->
                <ol class="conversation-rail mt-6 flex flex-col gap-6">
                    <li v-for="item in thread" :key="item.id" class="relative">
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

<style scoped>
/* The same rail the timeline hangs off, run behind the avatars so a thread of
   short gestures reads as one thing rather than as loose lines. The geometry
   matches FeedRail: a 36px avatar centres on 18px, so a 2px line sits at 17. */
.conversation-rail {
    position: relative;
}

.conversation-rail::before {
    content: '';
    position: absolute;
    left: 17px;
    top: 14px;
    bottom: 14px;
    width: 2px;
    background: linear-gradient(to bottom, var(--color-neutral-25), var(--color-neutral-50));
    border-radius: 2px;
}
</style>
