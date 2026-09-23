<script setup>
import { computed } from 'vue';
import { Link, usePage } from '@inertiajs/vue3';
import Avatar from './Avatar.vue';
import ContributedText from './ContributedText.vue';
import Icon from '../Ui/Icon.vue';
import { markableId, setMine } from '../../lib/mineMark.js';

const props = defineProps({
    // One ConversationItem: { id, kind, authorName, authorUrl, authorPhoto,
    // title, body, occurredAt, parentId, parentItemId, commentId, sourceUrl,
    // sourceHost, emoji, source, sourceName, sourceFavicon, mine }.
    item: { type: Object, required: true },
    // Rendered as a reply to somebody, one level deep only. Also how a response
    // read out of another site's thread hangs off the mention that carried it.
    nested: { type: Boolean, default: false },
});

defineEmits(['reply']);

/**
 * Each kind finishes the sentence its byline starts: "Jo Bloggs replied on
 * Thursday 3 September", with the source's title sitting between the two when
 * there is one.
 */
const KINDS = {
    comment: { icon: 'Comment01Icon', did: 'commented' },
    reply: { icon: 'MailReply01Icon', did: 'replied' },
    rsvp: { icon: 'Calendar01Icon', did: 'RSVP’d' },
    like: { icon: 'FavouriteIcon', did: 'liked this' },
    repost: { icon: 'ArrowReloadHorizontalIcon', did: 'reposted this' },
    bookmark: { icon: 'Bookmark01Icon', did: 'bookmarked this' },
    mention: { icon: 'Link02Icon', did: 'linked to this' },
    'mention-internal': { icon: 'Link02Icon', did: 'mentioned this' },
    reacji: { icon: null, did: 'reacted' },
};

const kind = computed(() => KINDS[props.item.kind] ?? KINDS.mention);

/** What each source calls the gesture, since only the wording differs. */
const SOURCE_WORDS = {
    strava: { like: 'gave kudos', reply: 'commented' },
    swarm: { like: 'liked this', reply: 'commented' },
};

/**
 * The verb for this response. A syndicated gesture uses the source's own word
 * for it (a kudo is not a like), falling back to the generic phrasing for
 * everything else.
 */
const did = computed(() => SOURCE_WORDS[props.item.source]?.[props.item.kind] ?? kind.value.did);

const via = computed(() => props.item.sourceName ?? props.item.sourceHost ?? null);

/**
 * One of my own entries, which is shown here as a convenience and carries no
 * microformats at all.
 *
 * The machine-readable fact is the link on the source's own h-entry. Repeating
 * it as an h-cite would tell a parser somebody responded to this post, and a
 * loose p-name would hoist up and rename the entry itself.
 */
const isInternal = computed(() => props.item.kind === 'mention-internal');

/**
 * What to call the entry this came from. EntryName decides it server-side, so
 * one of mine that has no title of its own arrives already named.
 */
const sourceLabel = computed(() => props.item.title ?? null);

/**
 * Whether to name the post a response came from.
 *
 * Only the kinds that point at a piece of writing. A gesture is one clean line
 * by design, and its title is whatever page the button happened to sit on.
 */
const showTitle = computed(() => Boolean(sourceLabel.value) && ['reply', 'mention', 'mention-internal'].includes(props.item.kind));

/**
 * The h-entry property this response is, in the microformats sense.
 *
 * Without one a nested h-cite parses as an unassigned child, so the page shows
 * responses without ever saying they are responses to this post. The set and
 * the embedded-h-cite shape both follow what aaronparecki.com publishes, which
 * emits nothing for a mention or an RSVP: those stay unassigned children.
 */
const PROPERTIES = {
    comment: 'p-comment',
    reply: 'p-comment',
    like: 'p-like',
    repost: 'p-repost',
    bookmark: 'p-bookmark',
};

const property = computed(() => PROPERTIES[props.item.kind] ?? null);

/**
 * The id to mark this reply as mine with, for the signed-in owner only. Null
 * for everyone else and for anything that is not a Strava or Swarm reply.
 */
const markable = computed(() => (usePage().props.signedIn === true ? markableId(props.item) : null));

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
        :class="[
            'relative',
            ! isInternal && 'h-cite',
            property,
            nested && 'response-nested ml-16',
            nested && ! item.lastNested && 'response-continues',
        ]"
    >
        <!-- The row is a box of its own for two reasons. Responses read out of
             this one's source sit after it, inside the h-cite rather than beside
             the avatar, so a reply to a reply is not published as a reply to me.
             And one of mine can carry a surface without moving the article: the
             elbow and the branch line are positioned against it, and a nested row
             already carries ml-16, which beats -mx-3 in the cascade, so padding
             the article would walk the avatar right and leave the line behind. -->
        <div :class="['group flex gap-3', item.mine && 'bg-neutral-25 rounded-lg px-3 py-2 -mx-3']">
            <!-- The mark sits on the avatar, since whose response this is what
                 the avatar already says. Where there is no hover to reveal it,
                 it moves to the end of the byline instead. -->
            <span class="response-avatar grid shrink-0 self-start">
                <Avatar
                    :name="item.authorName"
                    :photo="item.authorPhoto"
                    :mine="item.mine"
                    class="col-start-1 row-start-1"
                />

                <button
                    v-if="markable"
                    type="button"
                    class="col-start-1 row-start-1 flex items-center justify-center rounded-full bg-accent-50 text-accent-700 opacity-0 transition hover:bg-accent-100 focus-visible:opacity-100 group-hover:opacity-100 pointer-coarse:hidden"
                    :aria-pressed="item.mine"
                    :aria-label="item.mine ? 'Not mine' : 'Mark as mine'"
                    @click="setMine(markable, ! item.mine)"
                >
                    <Icon name="UserIcon" class="size-4" />
                </button>
            </span>

            <div class="min-w-0 flex-1">
                <!-- Inline flow, not flex: the byline is one sentence, so a long
                     title or the date wraps with the words rather than dropping to
                     a row of its own as an unbreakable box. Every space between the
                     pieces is written out, because Vue eats a newline-only gap. The
                     marker sits on the line with align-middle: an SVG has no
                     baseline of its own, so the browser synthesises one from its
                     bottom edge. -->
                <p class="text-sm">
                    <!-- Same tab: an author's own site is a normal onward link,
                         not an aside, so it needs no new-tab announcement. -->
                    <a
                        v-if="item.authorUrl"
                        :href="item.authorUrl"
                        rel="ugc nofollow noopener noreferrer"
                        class="p-author h-card rounded-sm font-semibold text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >{{ item.authorName }}</a>
                    <span v-else class="p-author font-semibold text-neutral-900">{{ item.authorName }}</span>
                    {{ ' ' }}
                    <span class="text-neutral-500">
                        <!-- The emoji carries its own space; the icon's margin is
                             the space after it. -->
                        <span v-if="item.emoji" aria-hidden="true">{{ item.emoji }}{{ ' ' }}</span>
                        <Icon v-else-if="kind.icon" :name="kind.icon" class="mb-0.5 mr-1.5 inline size-3.5 align-middle" />
                        <span>{{ did }}</span>

                        <span v-if="showTitle">{{ ' ' }}in{{ ' ' }}<component
                            :is="isInternal ? Link : 'a'"
                            :href="item.sourceUrl"
                            :rel="isInternal ? null : 'ugc nofollow noopener noreferrer'"
                            :class="[
                                'rounded-sm font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500',
                                ! isInternal && 'p-name u-url',
                            ]"
                        ><cite v-if="item.title" class="not-italic">{{ item.title }}</cite><template v-else>{{ sourceLabel }}</template></component></span>{{ ' ' }}on</span>
                    {{ ' ' }}
                    <time class="dt-published text-neutral-500" :datetime="item.occurredAt.iso">{{ item.occurredAt.label }} {{ item.occurredAt.offset }}</time>
                    {{ ' ' }}
                    <!-- Where it came from, closing the sentence rather than
                         interrupting it. A platform names itself; a webmention names
                         the site it was published on. A comment left here has no
                         elsewhere, so it says nothing. -->
                    <span v-if="via" class="text-neutral-500">via <img v-if="item.sourceFavicon" :src="item.sourceFavicon" alt="" loading="lazy" class="mb-0.5 mr-1 inline size-3.5 rounded-sm align-middle"><Icon v-else name="InternetIcon" class="mb-0.5 mr-1 inline size-3.5 align-middle" /><a
                        v-if="item.sourceUrl"
                        :href="item.sourceUrl"
                        rel="ugc nofollow noopener noreferrer"
                        class="rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >{{ via }}</a><template v-else>{{ via }}</template></span>
                    <button
                        v-if="markable"
                        type="button"
                        class="ml-1 hidden size-6 items-center justify-center rounded-full align-middle text-neutral-400 transition-colors hover:text-accent-500 focus-visible:text-accent-500 pointer-coarse:inline-flex"
                        :aria-pressed="item.mine"
                        :aria-label="item.mine ? 'Not mine' : 'Mark as mine'"
                        @click="setMine(markable, ! item.mine)"
                    >
                        <Icon :name="item.mine ? 'UserRemove01Icon' : 'UserAdd01Icon'" class="size-4" />
                    </button>
                </p>

                <ContributedText v-if="item.body?.length" :blocks="item.body" class="mt-2" />

                <!-- Only when the byline is not already naming the source, which
                     links to the same place and would give it two u-urls. -->
                <p v-if="item.body && item.sourceUrl && ! showTitle" class="mt-2 text-xs">
                    <a
                        :href="item.sourceUrl"
                        rel="ugc nofollow noopener noreferrer"
                        class="u-url rounded-sm text-neutral-500 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    >Read it on {{ item.sourceHost }}</a>
                </p>

                <button
                    v-if="item.commentId"
                    type="button"
                    class="mt-2 rounded-sm text-xs text-neutral-500 transition-colors hover:text-accent-500 focus-visible:text-accent-500"
                    @click="$emit('reply', item)"
                >
                    Reply
                </button>
            </div>
        </div>

        <slot />
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
    height: calc(1.5rem + (var(--text-sm) * var(--text-sm--line-height)) / 2);

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
    top: calc((var(--text-sm) * var(--text-sm--line-height)) / 2);
    bottom: -1.5rem;
    border-left: 1px solid var(--color-neutral-100);
}

/* Centre the avatar on the name line rather than on the whole block: half the
   difference between the avatar and that line's box lifts it into place. Derived
   from the type tokens so it follows if the scale changes. */
.response-avatar {
    margin-top: calc((var(--text-sm) * var(--text-sm--line-height) - 2.25rem) / 2);

    /* Positioned so it paints over the elbow running under it, the same way the
       main rail passes behind the avatars rather than stopping at them. */
    position: relative;
}
</style>
