<script setup>
import { computed, defineAsyncComponent, nextTick, ref } from 'vue';
import ReactionBar from './ReactionBar.vue';
import ResponseAsides from './ResponseAsides.vue';
import ResponseItem from './ResponseItem.vue';

// Its own chunk: the form carries the nonce fetch and the identity fields, and
// most readers never write anything.
const CommentForm = defineAsyncComponent(() => import('./CommentForm.vue'));

const props = defineProps({
    // One ConversationData: { type, id, url, reactions, responses }.
    conversation: { type: Object, required: true },
    // The page's Open Graph payload, shown under "Sharing this?".
    og: { type: Object, default: () => ({}) },
});

const replyingTo = ref(null);
const asides = ref(null);

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

    const byCommentId = new Map(
        all.filter((item) => item.commentId !== null).map((item) => [item.commentId, item]),
    );

    /**
     * The response a reply ultimately hangs off, however deep it was left.
     * Grouping by the immediate parent instead dropped a reply to a reply
     * entirely, because only top-level responses were ever asked for children.
     */
    const rootOf = (item) => {
        let current = item;

        // Bounded: a parent chain that somehow looped would hang the page
        // rather than merely render it wrong.
        for (let hops = 0; hops < 100; hops += 1) {
            if (current.parentId === null || ! byCommentId.has(current.parentId)) {
                break;
            }

            current = byCommentId.get(current.parentId);
        }

        return current;
    };

    const children = new Map();

    for (const item of all) {
        const root = rootOf(item);

        // Its own root: a top-level response, or a reply whose parent is not
        // here because it is held or deleted. Either way it stands on its own
        // rather than vanishing with the parent.
        if (root.id === item.id) {
            continue;
        }

        const siblings = children.get(root.id) ?? [];
        siblings.push(item);
        children.set(root.id, siblings);
    }

    const at = (item) => new Date(item.occurredAt.iso).getTime();
    const byOldest = (a, b) => at(a) - at(b);

    // A thread is ordered by its latest activity, not by when it started: a
    // reply today to a comment from last month makes that conversation the
    // newest thing here, and sorting on the parent alone would bury it where
    // nobody looks. The dates on screen explain the order without a label.
    const lastActivity = (root) => Math.max(
        at(root),
        ...(children.get(root.id) ?? []).map(at),
    );

    return all
        .filter((item) => rootOf(item).id === item.id)
        .sort((a, b) => lastActivity(b) - lastActivity(a))
        .flatMap((root) => {
            const replies = (children.get(root.id) ?? []).sort(byOldest);

            return [
                // groupId marks everything belonging to one conversation, which
                // is what the reply form is placed against: it opens at the end
                // of the thread, wherever in it you pressed Reply.
                { ...root, nested: false, groupId: root.id },
                // Every descendant sits at one indent, in time order. Depth is
                // stored truthfully and flattened here: past the first step in,
                // the indentation says less than the order does.
                ...replies.map((child, index) => ({
                    ...child,
                    nested: true,
                    groupId: root.id,
                    lastNested: index === replies.length - 1,
                })),
            ];
        });
});

/**
 * The response the reply form opens under: the last one in the thread being
 * replied to, rather than the one whose button was pressed. A form dropped
 * between two replies would break the branch line running down them, and the
 * reply will be posted to the end of the thread anyway, which is where the form
 * should sit to say so.
 */
const formFollows = computed(() => {
    if (! replyingTo.value) {
        return null;
    }

    const group = thread.value.filter((item) => item.groupId === replyingTo.value.groupId);

    return group[group.length - 1]?.id ?? null;
});

const heading = computed(() => {
    if (! thread.value.length) {
        return 'No responses yet';
    }

    return thread.value.length === 1 ? '1 response' : `${thread.value.length} responses`;
});

// Derived from the responses rather than sent separately, so the summary line
// can never disagree with the thread it summarises.
// A like is the binary gesture: a webmention like-of today, a kudo or a Swarm
// like later. Reacji are counted by the bar itself from its own buckets.
const likeCount = computed(() => thread.value.filter((item) => item.kind === 'like').length);

const replyCount = computed(() => thread.value.filter((item) => item.body?.length).length);
// Anything that carried something written, whoever wrote it and wherever from.

/**
 * The comment being answered, recorded as it actually happened. Depth is kept
 * whole in the database and flattened for display, so who answered whom is not
 * lost just because the thread is only ever drawn one step in.
 */
const replyParentId = computed(() => replyingTo.value?.commentId ?? null);

/** Open the reply form inside the thread, and take the reader to it. */
async function reply(item) {
    replyingTo.value = item;

    await nextTick();
    document.getElementById('reply-form')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>

<template>
    <!-- No rules anywhere in here. Separation is space and the weight of the
         headings, which is what stops a short entry looking like a form. -->
    <section aria-labelledby="responses">
        <!-- The heading is here whether or not anybody has said anything. An
             entry that opened straight onto a summary line and a text box had
             nothing naming what any of it was for, which read as debris at the
             bottom of the page rather than as a section. -->
        <h2 id="responses" class="scroll-mt-8 font-display text-section text-neutral-900">{{ heading }}</h2>

        <div class="mt-4 space-y-8">
            <!-- A summary line, not a labelled section: the counts read as part
                 of the entry rather than as a form to fill in. -->
            <ReactionBar
                :reactions="conversation.reactions"
                :like-count="likeCount"
                :reply-count="replyCount"
                :type="conversation.type"
                :id="conversation.id"
            />

            <!-- Says what to do, not that there is nothing here: the heading
                 already said that, and repeating it is the whole of the line. -->
            <p v-if="! thread.length" class="text-body text-neutral-500">
                Add a comment below, or
                <button
                    type="button"
                    class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                    @click="asides?.openWebmention()"
                >send me the link to your own post</button>.
            </p>

            <div v-else>
                <!-- One stream, every kind. A gesture renders as a single line
                     and a written response as a block, so the weight difference
                     comes from the content rather than from separate lists. -->
                <ol class="conversation-rail flex flex-col gap-6">
                    <template v-for="item in thread" :key="item.id">
                        <li class="relative">
                            <ResponseItem :item="item" :nested="item.nested" @reply="reply" />
                        </li>

                        <!-- Indented to where a reply's words start, not to its
                             avatar: the form holds what you are writing, so it
                             lines up with the writing above it. That is the 4rem
                             reply indent plus the 2.25rem avatar and its gap. -->
                        <li v-if="formFollows === item.id" id="reply-form" class="ml-28">
                            <CommentForm
                                :type="conversation.type"
                                :id="conversation.id"
                                :parent-id="replyParentId"
                                :replying-to="replyingTo.authorName"
                                @cancel="replyingTo = null"
                            />
                        </li>
                    </template>
                </ol>
            </div>
        </div>

        <!-- The comment box is the one primary control here, so it carries
             no heading of its own: a title above a single field is a label for
             a form that is trying not to look like one. -->
        <div class="mt-6">
            <!-- Only ever a new comment on the entry. Replying to somebody
                 happens inside the thread, against the response it answers. -->
            <CommentForm :type="conversation.type" :id="conversation.id" />

            <ResponseAsides ref="asides" :url="conversation.url" :og="og" />
        </div>

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
    bottom: 10px;
    width: 2px;
    background: linear-gradient(to bottom, var(--color-neutral-25), var(--color-neutral-50));
    border-radius: 2px;
}

/* The cap the timeline uses, on the same geometry: the line ends at 10px and
   the dot's centre sits exactly there, so the thread stops rather than being
   cut off. See FeedRail. */
.conversation-rail::after {
    content: '';
    position: absolute;
    left: 14px;
    bottom: 6px;
    width: 8px;
    height: 8px;
    border-radius: 9999px;
    background: var(--color-neutral-50);
}
</style>
