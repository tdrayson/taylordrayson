<script setup>
import { Link } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { cn } from '../../lib/cn.js';
import { csrf } from '../../lib/csrf.js';
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
    ? { row: 'gap-4', text: 'text-meta', icon: 'size-4', disc: 'size-5', discIcon: 'size-3.5', pip: 'size-5', pipIcon: 'size-3.5' }
    : { row: 'gap-5', text: 'text-body', icon: 'size-5', disc: 'size-6', discIcon: 'size-4', pip: 'size-6', pipIcon: 'size-4' }));

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
    wow: { icon: 'ShockedIcon', colour: 'var(--color-reaction-wow)' },
    haha: { icon: 'Relieved02Icon', colour: 'var(--color-reaction-haha)' },
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
        return 'No written responses yet';
    }

    return `${props.replyCount} written ${props.replyCount === 1 ? 'response' : 'responses'}`;
});

/** The optional gesture counts, each one only there when it happened. */
const gestures = computed(() => [
    { key: 'repost', icon: 'RepeatIcon', count: props.repostCount, one: 'repost', many: 'reposts' },
    { key: 'bookmark', icon: 'Bookmark01Icon', count: props.bookmarkCount, one: 'bookmark', many: 'bookmarks' },
].filter((gesture) => gesture.count > 0));

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
            <!-- The picker opens on hover for a mouse and on focus for a
                 keyboard; the control stays clickable either way. -->
            <div
                ref="group"
                class="relative"
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
                        'inline-flex items-center gap-1.5 rounded-full py-1 transition-colors',
                        sizes.text,
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                        mine ? 'text-accent-700' : 'text-neutral-500 hover:text-accent-700',
                        busy !== null && 'opacity-50',
                    )"
                    @click="press"
                >
                    <span
                        v-if="mine && glyph(mine)"
                        :class="['flex items-center justify-center rounded-full', sizes.disc]"
                        :style="discOf(glyph(mine))"
                        aria-hidden="true"
                    >
                        <Icon :name="glyph(mine).icon" :class="sizes.discIcon" />
                    </span>
                    <span v-else-if="mine" v-twemoji aria-hidden="true">{{ mine.emoji }}</span>
                    <Icon v-else name="ThumbsUpIcon" :class="sizes.icon" />
                    <!-- Zero is shown too. A count that appears only once it
                         is non-zero makes the line a different shape on every
                         entry, and a lone number reads as a stray mark. -->
                    <span class="tnum font-medium">{{ total }}</span>
                </button>

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
            </div>

            <!-- Not a link any more: the heading it used to jump to now sits
                 directly above this line. -->
            <Tooltip :label="responsesLabel" placement="top">
            <component
                :is="compact && url ? Link : 'span'"
                :href="compact && url ? `${url}#responses` : undefined"
                :class="[
                    'inline-flex items-center gap-1.5 text-neutral-500',
                    sizes.text,
                    compact && url && 'rounded-sm transition-colors hover:text-accent-700 focus-visible:text-accent-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                ]"
                :aria-label="responsesLabel"
            >
                <Icon name="Comment01Icon" :class="sizes.icon" />
                <span class="tnum font-medium">{{ replyCount }}</span>
            </component>
            </Tooltip>

            <Tooltip v-for="gesture in gestures" :key="gesture.key" :label="gestureLabel(gesture)" placement="top">
                <span :class="['inline-flex items-center gap-1.5 text-neutral-500', sizes.text]" :aria-label="gestureLabel(gesture)">
                    <Icon :name="gesture.icon" :class="sizes.icon" />
                    <span class="tnum font-medium">{{ gesture.count }}</span>
                </span>
            </Tooltip>

            <!-- Which reactions people actually picked. Overlapped so the row
                 stays short, and spread on hover so each can be pointed at for
                 its own count. -->
            <ul
                v-if="chosen.length"
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
                    <span class="reaction-count tnum text-caption text-neutral-500" aria-hidden="true">{{ bucket.count }}</span>
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
