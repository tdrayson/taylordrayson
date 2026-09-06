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
/**
 * Each kind finishes the sentence its byline starts, ending in "on" so the date
 * that follows is part of it: "Jo Bloggs replied on Thursday 3 September".
 */
const KINDS = {
    comment: { icon: 'Comment01Icon', says: 'commented on' },
    reply: { icon: 'MailReply01Icon', says: 'replied on' },
    rsvp: { icon: 'Calendar01Icon', says: 'RSVP’d on' },
    like: { icon: 'FavouriteIcon', says: 'liked this on' },
    repost: { icon: 'RepeatIcon', says: 'reposted this on' },
    bookmark: { icon: 'Bookmark01Icon', says: 'bookmarked this on' },
    mention: { icon: 'Link02Icon', says: 'linked to this on' },
    reacji: { icon: null, says: 'reacted on' },
};

const kind = computed(() => KINDS[props.item.kind] ?? KINDS.mention);

// A gesture with nothing written in it is one line, not a block. This is the
// weight difference a facepile would otherwise be needed for.
const isGesture = computed(() => ! props.item.body);

const via = computed(() => props.item.source ?? props.item.sourceHost ?? null);
</script>

<template>
    <!-- One avatar size for every kind: a smaller one on gestures narrowed the
         column and stepped their text left of everything else. The weight
         difference comes from whether there is a body, not from the avatar.
         It centres on the name line whether or not a body follows, so the
         avatars run down one axis however long each response is. -->
    <article
        :id="item.id"
        v-twemoji
        class="h-cite relative flex gap-3"
        :class="[nested && 'response-nested ml-16', nested && ! item.lastNested && 'response-continues']"
    >
        <Avatar
            class="response-avatar"
            :name="item.authorName"
            :photo="item.authorPhoto"
        />

        <div class="min-w-0 flex-1">
            <!-- Centred, not baselined: the row mixes two type sizes with an icon,
                 and an SVG has no baseline of its own, so the browser synthesises
                 one from its bottom edge and sits it on the line. Centring holds
                 the name, the marker and the date on one optical line. -->
            <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-meta">
                <!-- Same tab: an author's own site is a normal onward link,
                     not an aside, so it needs no new-tab announcement. -->
                <a
                    v-if="item.authorUrl"
                    :href="item.authorUrl"
                    rel="noopener noreferrer nofollow"
                    class="p-author h-card rounded-sm font-semibold text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >{{ item.authorName }}</a>
                <span v-else class="p-author font-semibold text-neutral-900">{{ item.authorName }}</span>

                <span class="inline-flex items-center gap-x-1.5 text-caption text-neutral-500">
                    <span v-if="item.emoji" aria-hidden="true">{{ item.emoji }}</span>
                    <Icon v-else-if="kind.icon" :name="kind.icon" class="size-3.5" />
                    <!-- Wrapped, so the flex gap sits between the marker and the
                         phrase whether the marker is an SVG or an emoji glyph. -->
                    <!-- Where it came from, only when the name is not already a
                         link to it. A webmention author's name carries their site,
                         so repeating the host says it twice; a syndicated gesture
                         has no profile to link, so the platform is named instead. -->
                    <span>{{ kind.says }}</span>
                </span>

                <time
                    class="dt-published text-caption text-neutral-500"
                    :datetime="item.occurredAt.iso"
                    :title="`${item.occurredAt.label} (UTC${item.occurredAt.offset})`"
                >{{ item.occurredAt.label }}</time>

                <!-- Where it came from, closing the sentence rather than
                     interrupting it. A platform names itself; a webmention names
                     the site it was published on. A comment left here has no
                     elsewhere, so it says nothing. -->
                <a
                    v-if="via && item.sourceUrl"
                    :href="item.sourceUrl"
                    rel="noopener noreferrer nofollow"
                    class="rounded-sm text-caption text-neutral-500 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                >via {{ via }}</a>
                <span v-else-if="via" class="text-caption text-neutral-500">via {{ via }}</span>
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

    /* Dropped from under the parent's text rather than from the main rail: on
       the rail it read as the thread continuing instead of branching off it.
       The parent's content starts one avatar column in (3rem), and the reply is
       indented past that (4rem), so the elbow turns in over the 1rem between. */
    left: -1rem;

    /* Run on to the avatar's centre rather than stopping at its left edge: a
       line that ends on the tangent of a pale circle reads as a gap. The avatar
       is lifted over it below. 1rem of turn plus half a 2.25rem avatar. */
    width: 2.125rem;

    /* Up into the gap above so it reads as coming from the parent, and down to
       this avatar's centre. With the avatar centred on its name line, that
       centre sits half a line box below the article top. */
    top: -1.5rem;
    height: calc(1.5rem + (var(--text-meta) * var(--text-meta--line-height)) / 2);

    border-left: 1px solid var(--color-neutral-100);
    border-bottom: 1px solid var(--color-neutral-100);
    border-bottom-left-radius: 0.5rem;
}

/* Carries the branch line past this reply to the next one, so a run of replies
   hangs off a single line rather than a stack of loose brackets. Absent on the
   last, which is where the line should stop. */
.response-continues::after {
    content: '';
    position: absolute;
    left: -1rem;
    top: calc((var(--text-meta) * var(--text-meta--line-height)) / 2);
    bottom: -1.5rem;
    border-left: 1px solid var(--color-neutral-100);
}

/* Centre the avatar on the name line rather than on the whole block: half the
   difference between the avatar and that line's box lifts it into place. Derived
   from the type tokens so it follows if the scale changes. */
.response-avatar {
    margin-top: calc((var(--text-meta) * var(--text-meta--line-height) - 2.25rem) / 2);

    /* Positioned so it paints over the elbow running under it, the same way the
       main rail passes behind the avatars rather than stopping at them. */
    position: relative;
}
</style>
