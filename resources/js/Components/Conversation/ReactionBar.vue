<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { cn } from '../../lib/cn.js';
import { csrf } from '../../lib/csrf.js';
import CountGroup from '../Ui/CountGroup.vue';
import CountSegment from '../Ui/CountSegment.vue';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

/**
 * The summary line under an entry: one total for the positive gestures, one for
 * the responses that carry prose, and the emoji actually chosen shown as a
 * stack beside them.
 *
 * A like and a reacji are different things and are stored apart: a like is
 * binary and arrives from a webmention or a syndicated copy, a reacji is
 * somebody picking an emoji here. They are summed only for this figure, because
 * to a reader they are all "somebody liked this".
 */
const props = defineProps({
    // [{ key, emoji, label, count, mine }] — every offered emoji, plus any that
    // arrived by webmention and matches none of them.
    reactions: { type: Array, default: () => [] },
    // Gestures with no emoji to show: webmention likes, and later kudos.
    likeCount: { type: Number, default: 0 },
    // Responses carrying prose, for the count beside the speech bubble.
    replyCount: { type: Number, default: 0 },
    // Shown only when somebody actually did one, so the row grows to fit the
    // entry rather than carrying two permanent zeroes. Replies are deliberately
    // not here: they already count in the speech bubble, and a number that is a
    // subset of another number is what needed explaining last time.
    repostCount: { type: Number, default: 0 },
    bookmarkCount: { type: Number, default: 0 },
    rsvpCount: { type: Number, default: 0 },
    mentionCount: { type: Number, default: 0 },
    type: { type: String, required: true },
    id: { type: Number, required: true },
    // 'compact' is the timeline feed: fifty full-size bars down a page is a lot
    // of furniture, and hover-to-spread fights a page being scrolled.
    variant: { type: String, default: 'full' },
    // The entry's own URL. Only the compact bar needs it, for the jump link.
    url: { type: String, default: null },
});

const compact = computed(() => props.variant === 'compact');

/** One place for every size that differs, rather than a ternary per element. */
const sizes = computed(() => (compact.value
    ? { row: 'gap-3', text: 'text-meta', icon: 'size-4', pip: 'size-5', pipIcon: 'size-3.5', group: 'sm', gap: 'gap-1', segment: 'gap-1 px-2 py-0.5' }
    : { row: 'gap-4', text: 'text-body', icon: 'size-5', pip: 'size-6', pipIcon: 'size-4', group: 'md', gap: 'gap-1.5', segment: 'gap-1.5 px-2.5 py-1' }));

/**
 * A white glyph on a coloured disc rather than an emoji glyph: an emoji is drawn
 * by whatever font the reader's platform ships, so it cannot be coloured, sized
 * or trusted to look the same twice.
 *
 * Keyed by our own reaction types. A key that is itself an emoji arrived by
 * webmention from somebody else's vocabulary, so that glyph is shown as sent.
 */
const GLYPHS = {
    like: { icon: 'ThumbsUpIcon', colour: 'var(--color-reaction-like)' },
    love: { icon: 'HeartIcon', colour: 'var(--color-reaction-love)' },
    celebrate: { icon: 'PartyIcon', colour: 'var(--color-reaction-celebrate)' },
    wow: { icon: 'SurpriseIcon', colour: 'var(--color-reaction-wow)' },
    haha: { icon: 'HappyIcon', colour: 'var(--color-reaction-haha)' },
    sad: { icon: 'Sad01Icon', colour: 'var(--color-reaction-sad)' },
};

const glyph = (bucket) => GLYPHS[bucket.key] ?? null;

/** A disc's fill. Every one is dark enough to carry the one glyph colour. */
const discOf = (g) => ({ background: g.colour, color: 'var(--color-reaction-glyph)' });

const buckets = ref([...props.reactions]);
const busy = ref(null);
const failed = ref(false);
const picking = ref(false);
const group = ref(null);
const control = ref(null);

/**
 * A touch device has no hover, so there is nothing to reveal the picker with.
 * There, tapping the control opens it and a second tap chooses; with a pointer,
 * tapping reacts straight away and hovering reveals the rest.
 */
const canHover = typeof window !== 'undefined' && window.matchMedia?.('(hover: hover)').matches;

/**
 * Close only when the pointer or focus has left the control and the picker
 * together. relatedTarget is where it went: still inside means it moved between
 * the two, which is the whole gesture rather than the end of it.
 */
function leave(event) {
    if (! group.value?.contains(event.relatedTarget)) {
        picking.value = false;
    }
}

/** A tap outside closes it, which is the only way out on a touch device. */
function closeOnOutside(event) {
    if (picking.value && ! group.value?.contains(event.target)) {
        picking.value = false;
    }
}

onMounted(() => document.addEventListener('pointerdown', closeOnOutside));
onBeforeUnmount(() => document.removeEventListener('pointerdown', closeOnOutside));

/** Escape closes and hands focus back, so a keyboard is never left inside. */
function dismiss() {
    picking.value = false;
    control.value?.focus();
}

// Buckets whose key is a word are ours to toggle; an emoji key came in by
// webmention and belongs to whoever sent it.
const isOurs = (bucket) => /^[a-z]+$/.test(bucket.key);

const chosen = computed(() => buckets.value.filter((bucket) => bucket.count > 0));
const total = computed(() => chosen.value.reduce((sum, bucket) => sum + bucket.count, 0) + props.likeCount);
const mine = computed(() => buckets.value.find((bucket) => bucket.mine) ?? null);

/**
 * Spelled out for the tooltip and the screen reader alike: the heading above
 * counts everything that arrived, this counts the ones that carry words, and
 * the two disagreeing without explanation is what made them confusing.
 */
const responsesLabel = computed(() => {
    if (! props.replyCount) {
        return 'No replies yet';
    }

    return `${props.replyCount} ${props.replyCount === 1 ? 'reply' : 'replies'}`;
});

/** The optional gesture counts, each one only there when it happened. */
const gestures = computed(() => [
    { key: 'repost', icon: 'ArrowReloadHorizontalIcon', colour: 'var(--color-count-reposts)', count: props.repostCount, one: 'repost', many: 'reposts' },
    { key: 'bookmark', icon: 'Bookmark01Icon', colour: 'var(--color-count-bookmarks)', count: props.bookmarkCount, one: 'bookmark', many: 'bookmarks' },
    { key: 'rsvp', icon: 'Calendar01Icon', colour: 'var(--color-count-rsvps)', count: props.rsvpCount, one: 'RSVP', many: 'RSVPs' },
    { key: 'mention', icon: 'Link02Icon', colour: 'var(--color-count-mentions)', count: props.mentionCount, one: 'mention', many: 'mentions' },
].filter((gesture) => gesture.count > 0));

/**
 * Whether anybody has responded at all. The box only frames a row that has
 * something in it; an unanswered entry keeps the light row, so a timeline of
 * mostly quiet entries is not a column of boxed zeroes.
 */
const hasResponses = computed(() => total.value > 0 || props.replyCount > 0 || gestures.value.length > 0);

const gestureLabel = (gesture) => `${gesture.count} ${gesture.count === 1 ? gesture.one : gesture.many}`;

/** What the summary reads out, since a row of emoji says nothing on its own. */
const summaryLabel = computed(() => {
    const parts = chosen.value.map((bucket) => `${bucket.count} ${bucket.label}`);

    if (props.likeCount) {
        parts.push(`${props.likeCount} liked from elsewhere`);
    }

    return parts.length ? parts.join(', ') : 'No reactions yet';
});

/**
 * Toggle a reaction, replacing the whole bar with the server's answer so a
 * click that raced somebody else's still lands on the true counts.
 */
async function toggle(bucket) {
    if (! isOurs(bucket) || busy.value) {
        return;
    }

    busy.value = bucket.key;
    failed.value = false;

    try {
        const response = await fetch(`/reactions/${props.type}/${props.id}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-XSRF-TOKEN': csrf() },
            credentials: 'same-origin',
            body: JSON.stringify({ type: bucket.key }),
        });

        if (! response.ok) {
            throw new Error(response.status);
        }

        buckets.value = (await response.json()).reactions;
    } catch {
        failed.value = true;
    } finally {
        busy.value = null;
        picking.value = false;
    }
}

/**
 * Clicking the control repeats your reaction, or gives the first one. Without
 * hover it opens the picker instead, since that is the only way to reach it.
 */
function press() {
    if (! canHover && ! picking.value) {
        picking.value = true;

        return;
    }

    toggle(mine.value ?? buckets.value.find(isOurs));
}
</script>

<template>
    <div data-testid="reaction-bar">
        <div :class="['flex items-center', sizes.row]">
            <!-- One joined box for the counts once somebody has responded, kept grey:
                 the reaction discs beside it are the only colour the row needs. -->
            <CountGroup :variant="hasResponses ? 'plain' : 'bare'" :size="sizes.group">
            <CountSegment :padded="false" colour="var(--color-count-reactions)" :muted="total === 0">
            <!-- The picker opens on hover for a mouse and on focus for a
                 keyboard; the control stays clickable either way. -->
            <div
                ref="group"
                class="relative flex"
                @mouseenter="picking = true"
                @mouseleave="leave"
                @focusin="picking = true"
                @focusout="leave"
                @keydown.escape="dismiss"
            >
                <button
                    ref="control"
                    type="button"
                    :disabled="busy !== null"
                    :aria-pressed="mine !== null"
                    :aria-label="mine ? `You reacted ${mine.label}` : 'React to this'"
                    :class="cn(
                        'inline-flex items-center',
                        hasResponses ? sizes.segment : cn('py-1', sizes.gap),
                        sizes.text,
                        // Inset, so the ring stays inside the segment rather than
                        // spilling over its neighbour's edge. The ink comes from
                        // the segment; the disc is what says you reacted.
                        'rounded-md focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500',
                        busy !== null && 'opacity-50',
                    )"
                    @click="press"
                >
                    <!-- Your own reaction's glyph in its colour, at the same size as
                         the icons beside it: a disc crammed into the box outweighed
                         them. Every reaction hue clears the 3:1 an icon needs on both
                         themes; the count stays grey, since text is where they fail. -->
                    <Icon
                        :name="mine && glyph(mine) ? glyph(mine).icon : 'ThumbsUpIcon'"
                        :class="sizes.icon"
                        :style="mine && glyph(mine) ? { color: glyph(mine).colour } : null"
                    />
                    <!-- Zero is shown too. A count that appears only once it
                         is non-zero makes the line a different shape on every
                         entry, and a lone number reads as a stray mark.
                         Weight, not colour, says you are in this count: the row
                         stays one colour and the disc keeps whichever it has. -->
                    <span class="tabular-nums" :class="mine ? 'font-bold' : 'font-medium'">{{ total }}</span>
                </button>

                <Transition name="picker-pop">
                <div v-show="picking" class="absolute bottom-full left-0 z-20 pb-1">
                    <ul class="flex gap-1 rounded-full border border-neutral-50 bg-neutral-0 px-2 py-1.5 shadow-lg">
                        <li v-for="bucket in buckets.filter(isOurs)" :key="bucket.key">
                            <Tooltip :label="bucket.label" placement="top">
                                <button
                                type="button"
                                :disabled="busy === bucket.key"
                                :aria-pressed="bucket.mine"
                                :aria-label="bucket.label"
                                class="inline-flex items-center justify-center rounded-full transition-transform hover:scale-125 focus-visible:scale-125 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                                @click="toggle(bucket)"
                            >
                                <span
                                    v-if="glyph(bucket)"
                                    class="flex size-8 items-center justify-center rounded-full"
                                    :style="discOf(glyph(bucket))"
                                    aria-hidden="true"
                                >
                                    <Icon :name="glyph(bucket).icon" class="size-5" />
                                </span>
                                <span v-else v-twemoji class="flex size-8 items-center justify-center text-lg" aria-hidden="true">{{ bucket.emoji }}</span>
                                </button>
                            </Tooltip>
                        </li>
                    </ul>
                </div>
                </Transition>
            </div>
            </CountSegment>

            <!-- On the feed this jumps to the entry's responses; on the entry
                 the heading it would jump to already sits above the line. -->
            <CountSegment
                :as="compact && url ? Link : 'span'"
                :href="compact && url ? `${url}#responses` : undefined"
                colour="var(--color-count-replies)"
                :muted="replyCount === 0"
                :aria-label="responsesLabel"
                :class="[sizes.text, compact && url && 'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-inset focus-visible:ring-accent-500']"
            >
                <Tooltip :label="responsesLabel" placement="top" :class="['items-center', sizes.gap]">
                    <Icon name="Comment01Icon" :class="sizes.icon" />
                    <span class="tnum">{{ replyCount }}</span>
                </Tooltip>
            </CountSegment>

            <CountSegment
                v-for="gesture in gestures"
                :key="gesture.key"
                :colour="gesture.colour"
                :aria-label="gestureLabel(gesture)"
                :class="sizes.text"
            >
                <Tooltip :label="gestureLabel(gesture)" placement="top" :class="['items-center', sizes.gap]">
                    <Icon :name="gesture.icon" :class="sizes.icon" />
                    <span class="tnum">{{ gesture.count }}</span>
                </Tooltip>
            </CountSegment>
            </CountGroup>

            <!-- Which reactions people actually picked. Overlapped so the row
                 stays short, and spread on hover so each can be pointed at for
                 its own count.

                 Only worth drawing once there is a mix: a single kind is
                 already named by the button on the left, so the pile would be
                 the same glyph and the same number said twice. -->
            <ul
                v-if="chosen.length > 1"
                :class="['reaction-pile flex items-center rounded-full focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500', compact && 'is-static']"
                :tabindex="compact ? -1 : 0"
                :aria-label="summaryLabel"
            >
                <li
                    v-for="bucket in chosen"
                    :key="bucket.key"
                    class="reaction-item flex items-center"
                >
                    <Tooltip :label="`${bucket.count} ${bucket.label}`" placement="top">
                    <span
                        :class="['reaction-pip flex items-center justify-center rounded-full ring-2 ring-neutral-0', sizes.pip]"
                        :style="glyph(bucket) ? discOf(glyph(bucket)) : { background: 'var(--color-neutral-25)' }"
                    >
                        <Icon v-if="glyph(bucket)" :name="glyph(bucket).icon" :class="sizes.pipIcon" />
                        <span v-else v-twemoji class="text-caption text-neutral-900" aria-hidden="true">{{ bucket.emoji }}</span>
                    </span>

                    <!-- Its own count, revealed with the spread so each disc can
                         be read rather than guessed at. -->
                    <span :class="['reaction-count tabular-nums font-medium text-neutral-500', sizes.text]" aria-hidden="true">{{ bucket.count }}</span>
                    </Tooltip>
                </li>
            </ul>
        </div>

        <p v-if="failed" class="mt-2 text-caption text-red-600">
            That did not save. Try again in a moment.
        </p>
    </div>
</template>

<style scoped>
/* The same curve and distance as the tooltip and the link preview, so every
   popup on the site arrives the same way. Grown from the bottom edge, which is
   the one anchored to the control. */
.picker-pop-enter-active,
.picker-pop-leave-active {
    transition:
        opacity 0.14s ease,
        scale 0.19s cubic-bezier(0.16, 1, 0.3, 1),
        translate 0.19s cubic-bezier(0.16, 1, 0.3, 1);
    transform-origin: bottom left;
}

.picker-pop-enter-from,
.picker-pop-leave-to {
    opacity: 0;
    scale: 0.92;
    translate: 0 8px;
}

@media (prefers-reduced-motion: reduce) {
    .picker-pop-enter-active,
    .picker-pop-leave-active {
        transition: opacity 0.14s ease;
    }

    .picker-pop-enter-from,
    .picker-pop-leave-to {
        scale: 1;
        translate: none;
    }
}

/* Overlapped at rest so a handful of kinds stay one short mark, and spread on
   hover so each is a target of its own and its title can be read. The overlap
   is barely more than the ring: a disc is 1.5rem and the glyph fills it, so
   anything deeper clips the glyph rather than just the disc, and the row reads
   as one smudge instead of as several things. */
.reaction-item {
    margin-left: -0.1875rem;
    transition: margin-left 150ms ease;
}

.reaction-item:first-child {
    margin-left: 0;
}

.reaction-pile:not(.is-static):hover .reaction-item,
.reaction-pile:not(.is-static):focus-within .reaction-item,
.reaction-pile:not(.is-static):focus .reaction-item {
    margin-left: 0.375rem;
}

.reaction-pile:not(.is-static):hover .reaction-item:first-child,
.reaction-pile:not(.is-static):focus-within .reaction-item:first-child,
.reaction-pile:not(.is-static):focus .reaction-item:first-child {
    margin-left: 0;
}

/* Hidden by width rather than display, so the reveal can be animated and the
   discs slide apart instead of jumping. */
.reaction-count {
    max-width: 0;
    overflow: hidden;
    opacity: 0;
    transition: max-width 150ms ease, opacity 150ms ease, margin-left 150ms ease;
}

.reaction-pile:not(.is-static):hover .reaction-count,
.reaction-pile:not(.is-static):focus-within .reaction-count,
.reaction-pile:not(.is-static):focus .reaction-count {
    max-width: 2rem;
    margin-left: 0.25rem;
    opacity: 1;
}

@media (prefers-reduced-motion: reduce) {
    .reaction-item,
    .reaction-count {
        transition: none;
    }
}
</style>
