<script setup>
import { computed } from 'vue';
import Avatar from './Avatar.vue';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ConversationItem: { id, kind, authorName, authorUrl, authorPhoto,
    // body, occurredAt, parentId, commentId, sourceUrl, sourceHost, emoji }.
    item: { type: Object, required: true },
    // Rendered as a reply to somebody, one level deep only.
    nested: { type: Boolean, default: false },
});

defineEmits(['reply']);

/**
 * What each kind did, as an icon and a phrase. A comment gets no phrase: it is
 * the ordinary case, and saying "commented" under every one is noise.
 */
const KINDS = {
    comment: { icon: 'Comment01Icon', says: null },
    reply: { icon: 'MailReply01Icon', says: 'replied' },
    rsvp: { icon: 'Calendar01Icon', says: 'RSVP’d' },
    like: { icon: 'FavouriteIcon', says: 'liked this' },
    repost: { icon: 'RepeatIcon', says: 'reposted this' },
    bookmark: { icon: 'Bookmark01Icon', says: 'bookmarked this' },
    mention: { icon: 'Link01Icon', says: 'linked to this' },
    reacji: { icon: null, says: 'reacted' },
};

const kind = computed(() => KINDS[props.item.kind] ?? KINDS.mention);

// A gesture with nothing written in it is one line, not a block. This is the
// weight difference a facepile would otherwise be needed for.
const isGesture = computed(() => ! props.item.body);
</script>

<template>
    <!-- One avatar size for every kind: a smaller one on gestures narrowed the
         column and stepped their text left of everything else. The weight
         difference comes from whether there is a body, not from the avatar.
         It centres on the name line whether or not a body follows, so the
         avatars run down one axis however long each response is. -->
    <article
        :id="item.id"
        class="h-cite relative flex gap-3"
        :class="[nested && 'response-nested ml-6 pl-6 sm:ml-11']"
    >
        <Avatar
            class="response-avatar"
            :name="item.authorName"
            :photo="item.authorPhoto"
        />

        <div class="min-w-0 flex-1">
            <p class="flex flex-wrap items-baseline gap-x-2 text-meta">
                <!-- Same tab: an author's own site is a normal onward link,
                     not an aside, so it needs no new-tab announcement. -->
                <a
                    v-if="item.authorUrl"
                    :href="item.authorUrl"
                    rel="noopener noreferrer nofollow"
                    class="p-author h-card rounded-sm font-semibold text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >{{ item.authorName }}</a>
                <span v-else class="p-author font-semibold text-neutral-900">{{ item.authorName }}</span>

                <span v-if="kind.says || item.emoji" class="inline-flex items-center gap-1 text-caption text-neutral-500">
                    <span v-if="item.emoji" aria-hidden="true">{{ item.emoji }}</span>
                    <Icon v-else-if="kind.icon" :name="kind.icon" class="size-3.5" />
                    <!-- Wrapped, so the flex gap sits between the marker and the
                         phrase whether the marker is an SVG or an emoji glyph. -->
                    <!-- Where it came from, only when the name is not already a
                         link to it. A webmention author's name carries their site,
                         so repeating the host says it twice; a syndicated gesture
                         has no profile to link, so the platform is named instead. -->
                    <span>
                        {{ kind.says }}
                        <template v-if="isGesture && item.source">on {{ item.source }}</template>
                        <template v-else-if="isGesture && ! item.authorUrl && item.sourceHost">from {{ item.sourceHost }}</template>
                    </span>
                </span>

                <time
                    class="dt-published text-caption text-neutral-500"
                    :datetime="item.occurredAt.iso"
                    :title="`${item.occurredAt.label} (UTC${item.occurredAt.offset})`"
                >{{ item.occurredAt.label }}</time>
            </p>

            <p v-if="item.body" class="e-content mt-1 whitespace-pre-line text-body text-neutral-900">{{ item.body }}</p>

            <p v-if="item.body && item.sourceUrl" class="mt-1 text-caption">
                <a
                    :href="item.sourceUrl"
                    rel="noopener noreferrer nofollow"
                    class="u-url rounded-sm text-neutral-500 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >Read it on {{ item.sourceHost }}</a>
            </p>

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

<style scoped>
/* A reply hangs off its parent with an elbow rather than a full-height rule:
   the line drops from the parent and turns in, which says "this answers that"
   where a bare left border only says "this is indented". Only ever one level
   deep, so there is no ladder of them. */
.response-nested::before {
    content: '';
    position: absolute;
    left: 0;
    top: calc(-0.75rem - 1px);
    bottom: 50%;
    width: 1rem;
    border-left: 1px solid var(--color-neutral-50);
    border-bottom: 1px solid var(--color-neutral-50);
    border-bottom-left-radius: 0.5rem;
}

/* Centre the avatar on the name line rather than on the whole block: half the
   difference between the avatar and that line's box lifts it into place. Derived
   from the type tokens so it follows if the scale changes. */
.response-avatar {
    margin-top: calc((var(--text-meta) * var(--text-meta--line-height) - 2.25rem) / 2);
}
</style>
