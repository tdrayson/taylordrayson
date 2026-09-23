<script setup>
import { computed, defineAsyncComponent, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
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
 * The thread and the counts as they stand now, not as the page was loaded: a
 * reaction answers with the true counts and an approved comment comes back
 * whole, so both land here rather than waiting for the next full load.
 */
const responses = ref([...props.conversation.responses]);
const reactions = ref([...props.conversation.reactions]);

watch(() => props.conversation, (value) => {
    responses.value = [...value.responses];
    reactions.value = [...value.reactions];
});

/**
 * A comment that is already public, put where the server would have put it.
 * One held for moderation has nothing to show, and says so in the form.
 */
function posted(result) {
    if (! result.response || responses.value.some((item) => item.id === result.response.id)) {
        return;
    }

    responses.value = [...responses.value, result.response];
}

/**
 * The thread: oldest conversation first, but each reply kept under the response
 * it answers. Sorting the whole list by date alone put a reply above its own
 * parent, which reads as a non-sequitur.
 *
 * Flattened to one level, so a reply to a reply sits beside its siblings rather
 * than stepping further right forever. Replies run oldest first within a thread
 * too, so the whole list reads forwards.
 */
const thread = computed(() => {
    const all = responses.value;

    const byCommentId = new Map(
        all.filter((item) => item.commentId !== null).map((item) => [item.commentId, item]),
    );

    const byItemId = new Map(all.map((item) => [item.id, item]));

    /**
     * What this response answers: a comment left here, or the mention it
     * arrived nested inside when it was read out of somebody else's thread.
     * Null when it stands on its own, or when its parent is not shown.
     */
    const parentOf = (item) => byItemId.get(item.parentItemId)
        ?? (item.parentId === null ? null : byCommentId.get(item.parentId))
        ?? null;

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
            const parent = parentOf(current);

            if (parent === null) {
                break;
            }

            current = parent;
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
    // reply today to a comment from last month belongs at the bottom with the
    // rest of today's responses, not back where that conversation began. The
    // dates on screen explain the order without a label.
    const lastActivity = (root) => Math.max(
        at(root),
        ...(children.get(root.id) ?? []).map(at),
    );

    return all
        .filter((item) => rootOf(item).id === item.id)
        .sort((a, b) => lastActivity(a) - lastActivity(b))
        .flatMap((root) => {
            const replies = (children.get(root.id) ?? []).sort(byOldest);

            // A response read out of another site's thread is published inside
            // its parent's h-cite, so it is handed to the parent to render
            // rather than listed beside it: flattened onto the entry it would
            // parse as a direct reply to me. Everything else keeps its own row.
            const carried = replies.filter((item) => item.parentItemId !== null);
            const listed = replies.filter((item) => item.parentItemId === null);

            const indented = (items) => items.map((child, index) => ({
                ...child,
                nested: true,
                groupId: root.id,
                lastNested: index === items.length - 1,
                carried: [],
            }));

            return [
                // groupId marks everything belonging to one conversation, which
                // is what the reply form is placed against: it opens at the end
                // of the thread, wherever in it you pressed Reply.
                { ...root, nested: false, groupId: root.id, carried: indented(carried) },
                // Every descendant sits at one indent, in time order. Depth is
                // stored truthfully and flattened here: past the first step in,
                // the indentation says less than the order does.
                ...indented(listed),
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

/**
 * Counted by what each thing IS, so no interaction lands in two figures.
 *
 * Counting the written ones by "carries a body" instead put a mention with
 * prose in the speech bubble and also in the mention tally, and left the
 * heading as a total nobody could reach by adding up what was beside it.
 */
const countOf = (...kinds) => responses.value.filter((item) => kinds.includes(item.kind)).length;

// On-site clicks plus the gestures that mean the same thing from somebody
// else's site: a like sent by webmention, and a single-emoji reply.
const onSite = computed(() => reactions.value.reduce((sum, bucket) => sum + bucket.count, 0));
const likeCount = computed(() => countOf('like', 'reacji'));
const reactionCount = computed(() => onSite.value + likeCount.value);

const replyCount = computed(() => countOf('comment', 'reply'));
const repostCount = computed(() => countOf('repost'));
const bookmarkCount = computed(() => countOf('bookmark'));
const rsvpCount = computed(() => countOf('rsvp'));
// My own entries count alongside everyone else's links: the heading has to add
// up to what the row beside it shows, whoever wrote the thing that links here.
const mentionCount = computed(() => countOf('mention', 'mention-internal'));

// Every kind, each counted once, which is what makes the heading add up.
const total = computed(() => reactionCount.value
    + replyCount.value
    + repostCount.value
    + bookmarkCount.value
    + rsvpCount.value
    + mentionCount.value);

const heading = computed(() => {
    if (! total.value) {
        return 'No interactions yet';
    }

    return total.value === 1 ? '1 interaction' : `${total.value} interactions`;
});

/**
 * The comment being answered, recorded as it actually happened. Depth is kept
 * whole in the database and flattened for display, so who answered whom is not
 * lost just because the thread is only ever drawn one step in.
 */
const replyParentId = computed(() => replyingTo.value?.commentId ?? null);

/** Input that means the reader has taken over the scroll. */
const RELEASE_EVENTS = ['wheel', 'touchstart', 'keydown'];

let release = () => {};

/**
 * Arrived from a feed card's replies link. Images and maps above load after the
 * jump and push the heading back down, so hold it at the top until the page
 * settles or the reader scrolls.
 */
onMounted(() => {
    const heading = document.getElementById('responses');

    if (window.location.hash !== '#responses' || ! heading) {
        return;
    }

    const observer = new ResizeObserver(() => heading.scrollIntoView());

    release = () => {
        observer.disconnect();
        RELEASE_EVENTS.forEach((name) => window.removeEventListener(name, release));
    };

    observer.observe(document.body);
    RELEASE_EVENTS.forEach((name) => window.addEventListener(name, release, { passive: true }));
    setTimeout(release, 3000);
});

onBeforeUnmount(() => release());

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
        <h2 id="responses" class="scroll-mt-8 font-display text-lg font-bold leading-tight tracking-tight text-neutral-900">{{ heading }}</h2>

        <div class="mt-4 space-y-8">
            <!-- A summary line, not a labelled section: the counts read as part
                 of the entry rather than as a form to fill in. -->
            <ReactionBar
                :reactions="reactions"
                :like-count="likeCount"
                :reply-count="replyCount"
                :repost-count="repostCount"
                :bookmark-count="bookmarkCount"
                :rsvp-count="rsvpCount"
                :mention-count="mentionCount"
                :type="conversation.type"
                :id="conversation.id"
                @reacted="reactions = $event"
            />

            <div v-if="thread.length">
                <!-- One stream, every kind. A gesture renders as a single line
                     and a written response as a block, so the weight difference
                     comes from the content rather than from separate lists. -->
                <ol :class="['flex flex-col gap-6', conversation.responses.length > 1 && 'conversation-rail']">
                    <template v-for="item in thread" :key="item.id">
                        <li class="relative">
                            <ResponseItem :item="item" :nested="item.nested" @reply="reply">
                                <!-- Inside the parent's article, so it is inside
                                     its h-cite. The spacing matches the gap the
                                     outer list uses, so the thread looks the same
                                     as it did when every response was a sibling. -->
                                <ol v-if="item.carried.length" class="mt-6 flex flex-col gap-6">
                                    <li v-for="child in item.carried" :key="child.id" class="relative">
                                        <ResponseItem :item="child" nested @reply="reply" />
                                    </li>
                                </ol>
                            </ResponseItem>
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
                                @posted="posted"
                            />
                        </li>
                    </template>
                </ol>
            </div>
        </div>

        <!-- The comment box is the one primary control here, so it carries
             no heading of its own: a title above a single field is a label for
             a form that is trying not to look like one. -->
        <div class="mt-10">
            <!-- Above the box in every state, because the two ways to answer
                 are not both visible: one is a text field and the other is a
                 panel further down that nobody would think to open. -->
            <p class="mb-3 text-base text-neutral-500">
                Add a comment, or
                <!-- Not prevented: following the hash moves the Tab starting
                     point to the panel it opens. -->
                <a
                    href="#send-a-link"
                    class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    @click="asides?.openWebmention()"
                >send me the link to your own post</a>.
            </p>

            <!-- Only ever a new comment on the entry. Replying to somebody
                 happens inside the thread, against the response it answers. -->
            <CommentForm :type="conversation.type" :id="conversation.id" @posted="posted" />

            <ResponseAsides ref="asides" :url="conversation.url" :og="og" />
        </div>

    </section>
</template>

<style scoped>
/* The same rail the timeline hangs off, run behind the avatars so a thread of
   short gestures reads as one thing rather than as loose lines. The geometry
   matches FeedRail: a 36px avatar centres on 18px, so a 2px line sits at 17.

   Only drawn from two responses up: one response is not a thread, and the cap
   below has nowhere to sit but on top of the single avatar. Counted over the
   whole conversation, not the rows in this list: a response read out of another
   site's thread renders inside its parent and would otherwise not count. */
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
