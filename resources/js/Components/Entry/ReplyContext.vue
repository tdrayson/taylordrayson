<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ResponseData: { kind, label, property, url, title, rsvp, host,
    // favicon, internal, cited: { title, authorName, authorPhoto, quote, published } }.
    response: { type: Object, required: true },
});

/** The same markers ResponseContext.vue uses for these verbs. */
const ICONS = {
    reply: 'MailReply01Icon',
    like: 'FavouriteIcon',
    repost: 'RepeatIcon',
    rsvp: 'Calendar01Icon',
};

const icon = computed(() => ICONS[props.response.kind] ?? 'Link02Icon');

const cited = computed(() => props.response.cited ?? null);

// Only a real photo, and only beside a real name. No initials fallback: a
// monogram for a stranger reads as a broken image rather than a face.
const photo = computed(() => (cited.value?.authorName ? cited.value.authorPhoto : null));

/** Whether there is anything of theirs to set behind the rule at all. */
const hasQuote = computed(() => Boolean(cited.value?.title || cited.value?.quote));

/** The microformats property the post carries: in-reply-to, like-of, repost-of. */
const property = computed(() => `u-${props.response.property}`);
</script>

<template>
    <!-- Inline flow, not flex: the title is words in a sentence, so it wraps
         with the rest of the line rather than as one unbreakable box. -->
    <div class="text-sm text-neutral-500">
        <Icon :name="icon" class="mb-0.5 mr-1.5 inline size-3.5 align-middle" />
        <data v-if="response.rsvp" class="p-rsvp" :value="response.rsvp">{{ response.label }}</data>
        <span v-else>{{ response.label }}</span>
        {{ ' ' }}
        <!-- The target this answers, grouped under one h-cite so a parser reads
             it (and any quote below) as the cited post, not folded into this
             entry's own properties like the p-rsvp above. `contents` keeps the
             grouping invisible as a box: the line above still flows as one sentence. -->
        <div :class="['contents', 'h-cite', property]">
            <!-- One of mine is named exactly as the timeline card names it, and
                 links in-app. -->
            <Link
                v-if="response.internal"
                :href="response.url"
                class="u-url rounded-sm font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500"
            >
                <img v-if="response.favicon" :src="response.favicon" alt="" loading="lazy" class="mb-0.5 mr-1.5 inline size-3.5 rounded-sm align-middle">
                <cite class="p-name not-italic">{{ response.title }}</cite>
            </Link>

            <!-- Somebody else's: their name when the page gave one, otherwise just
                 that it was a post, so the site is never named twice. -->
            <span v-else-if="cited?.authorName" class="p-author h-card font-medium text-neutral-700">
                <img v-if="photo" :src="photo" alt="" class="u-photo mb-0.5 mr-1.5 inline size-5 rounded-full object-cover align-middle">{{ cited.authorName }}
            </span>
            <a
                v-else
                :href="response.url"
                class="u-url underline decoration-neutral-100 underline-offset-2 hover:text-accent-700"
            >a post</a>

            <!-- A parser needs a u-url wherever it can find one: when the author
                 is named but nothing else is a link, the host carries it instead
                 of sitting there as plain text. -->
            <template v-if="response.host">
                <span v-if="cited?.authorName">{{ ' ' }}on <a :href="response.url" class="u-url underline decoration-neutral-100 underline-offset-2 hover:text-accent-700">{{ response.host }}</a></span>
                <span v-else>{{ ' ' }}on {{ response.host }}</span>
            </template>

            <!-- Their words behind the same rule a quote uses, on their own row. -->
            <!-- The `quote` slot lets the editor swap their words for an input in place. -->
            <div v-if="!response.internal && (hasQuote || $slots.quote)" class="mt-2 border-l-2 border-neutral-100 pl-4">
                <a v-if="cited?.title" :href="response.url" class="u-url block font-semibold text-neutral-900 hover:text-accent-700">
                    <cite class="p-name not-italic">{{ cited.title }}</cite>
                </a>
                <div v-if="$slots.quote" :class="cited?.title && 'mt-1'">
                    <slot name="quote" />
                </div>
                <p v-else-if="cited.quote" :class="['p-content text-neutral-600', cited.title && 'mt-1']">{{ cited.quote }}</p>
                <a v-if="cited?.published" :href="response.url" class="u-url mt-2 inline-block text-xs text-neutral-500 hover:text-accent-700">
                    <time class="dt-published" :datetime="cited.published.iso">{{ cited.published.label }} {{ cited.published.offset }}</time>
                </a>
            </div>
        </div>
    </div>
</template>
