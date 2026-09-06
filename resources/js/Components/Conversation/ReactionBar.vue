<script setup>
import { computed, ref } from 'vue';
import { cn } from '../../lib/cn.js';
import Icon from '../Ui/Icon.vue';

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
    type: { type: String, required: true },
    id: { type: Number, required: true },
});

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
    haha: { icon: 'LaughingIcon', colour: 'var(--color-reaction-haha)' },
    sad: { icon: 'CryingIcon', colour: 'var(--color-reaction-sad)' },
};

const glyph = (bucket) => GLYPHS[bucket.key] ?? null;

const buckets = ref([...props.reactions]);
const busy = ref(null);
const failed = ref(false);
const picking = ref(false);
const group = ref(null);
const control = ref(null);

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
            headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
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

/** Clicking the control itself repeats your reaction, or gives the first one. */
function toggleDefault() {
    toggle(mine.value ?? buckets.value.find(isOurs));
}
</script>

<template>
    <div>
        <div class="flex items-center gap-5">
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
                        'inline-flex items-center gap-1.5 rounded-full py-1 text-meta transition-colors',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                        mine ? 'text-accent-700' : 'text-neutral-500 hover:text-accent-700',
                        busy !== null && 'opacity-50',
                    )"
                    @click="toggleDefault"
                >
                    <span
                        v-if="mine && glyph(mine)"
                        class="flex size-5 items-center justify-center rounded-full text-neutral-0"
                        :style="{ background: glyph(mine).colour }"
                        aria-hidden="true"
                    >
                        <Icon :name="glyph(mine).icon" class="size-3" />
                    </span>
                    <span v-else-if="mine" aria-hidden="true">{{ mine.emoji }}</span>
                    <Icon v-else name="ThumbsUpIcon" class="size-4" />
                    <span v-if="total" class="tnum font-medium">{{ total }}</span>
                </button>

                <div v-show="picking" class="absolute bottom-full left-0 z-20 pb-1">
                    <ul class="flex gap-1 rounded-full border border-neutral-50 bg-neutral-0 px-2 py-1.5 shadow-lg">
                        <li v-for="bucket in buckets.filter(isOurs)" :key="bucket.key">
                            <button
                                type="button"
                                :disabled="busy === bucket.key"
                                :aria-pressed="bucket.mine"
                                :aria-label="bucket.label"
                                :title="bucket.label"
                                class="inline-flex items-center justify-center rounded-full transition-transform hover:scale-125 focus-visible:scale-125 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
                                @click="toggle(bucket)"
                            >
                                <span
                                    v-if="glyph(bucket)"
                                    class="flex size-8 items-center justify-center rounded-full text-neutral-0"
                                    :style="{ background: glyph(bucket).colour }"
                                    aria-hidden="true"
                                >
                                    <Icon :name="glyph(bucket).icon" class="size-4" />
                                </span>
                                <span v-else class="flex size-8 items-center justify-center text-lg" aria-hidden="true">{{ bucket.emoji }}</span>
                            </button>
                        </li>
                    </ul>
                </div>
            </div>

            <span v-if="replyCount" class="inline-flex items-center gap-1.5 text-meta text-neutral-500">
                <Icon name="Comment01Icon" class="size-4" />
                <span class="tnum font-medium">{{ replyCount }}</span>
            </span>

            <!-- Which reactions people actually picked. Overlapped so the row
                 stays short, and spread on hover so each can be pointed at for
                 its own count. -->
            <ul v-if="chosen.length" class="reaction-pile flex items-center" :aria-label="summaryLabel">
                <li
                    v-for="bucket in chosen"
                    :key="bucket.key"
                    class="reaction-pip flex size-5 items-center justify-center rounded-full text-neutral-0 ring-2 ring-neutral-0"
                    :style="glyph(bucket) ? { background: glyph(bucket).colour } : { background: 'var(--color-neutral-25)' }"
                    :title="`${bucket.count} ${bucket.label}`"
                >
                    <Icon v-if="glyph(bucket)" :name="glyph(bucket).icon" class="size-3" />
                    <span v-else class="text-caption text-neutral-900" aria-hidden="true">{{ bucket.emoji }}</span>
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
   hover so each is a target of its own and its title can be read. */
.reaction-pip {
    margin-left: -0.375rem;
    transition: margin-left 150ms ease;
}

.reaction-pip:first-child {
    margin-left: 0;
}

.reaction-pile:hover .reaction-pip,
.reaction-pile:focus-within .reaction-pip {
    margin-left: 0.125rem;
}

.reaction-pile:hover .reaction-pip:first-child,
.reaction-pile:focus-within .reaction-pip:first-child {
    margin-left: 0;
}

@media (prefers-reduced-motion: reduce) {
    .reaction-pip {
        transition: none;
    }
}
</style>
