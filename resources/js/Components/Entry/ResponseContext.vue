<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ResponseData: { kind, label, property, url, title, rsvp, host,
    // favicon, preview }.
    response: { type: Object, required: true },
});

/** What each kind did, using the markers the conversation already uses for these verbs. */
const ICONS = {
    reply: 'MailReply01Icon',
    like: 'FavouriteIcon',
    repost: 'RepeatIcon',
    rsvp: 'Calendar01Icon',
};

const icon = computed(() => ICONS[props.response.kind] ?? 'Link02Icon');

// One of mine is an ordinary internal link; anybody else's leaves the site.
const internal = computed(() => props.response.preview !== null);

/**
 * The microformats property this link carries, which is what makes the post a
 * reply rather than a post that happens to contain a link.
 */
const property = computed(() => `u-${props.response.property}`);
</script>

<template>
    <!-- One line, built like a conversation byline: the marker, what I did, and
         the thing I did it to. Whatever the post says follows underneath. -->
    <p class="flex flex-wrap items-center gap-x-1.5 gap-y-1 text-meta text-neutral-500">
        <Icon :name="icon" class="size-3.5 shrink-0" />
        <!-- An RSVP's answer is the verb, so it is the label, and the element
             carries the machine-readable value the wording spells out. -->
        <data v-if="response.rsvp" class="p-rsvp" :value="response.rsvp">{{ response.label }}</data>
        <span v-else>{{ response.label }}</span>

        <component
            :is="internal ? Link : 'a'"
            :href="response.url"
            :rel="internal ? null : 'noopener'"
            :class="[
                'h-cite inline-flex min-w-0 max-w-full items-center gap-1.5 rounded-sm font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                property,
            ]"
        >
            <img v-if="response.favicon" :src="response.favicon" alt="" loading="lazy" class="size-3.5 shrink-0 rounded-sm">
            <cite class="p-name truncate not-italic">{{ response.title }}</cite>
        </component>

        <!-- Outside the link, because only the name is the p-name: the site is
             where it lives, not what it is called. -->
        <span v-if="response.host">on {{ response.host }}</span>
    </p>
</template>
