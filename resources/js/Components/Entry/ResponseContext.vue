<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ResponseData: { kind, label, property, url, title, rsvp, host,
    // favicon, internal }.
    response: { type: Object, required: true },
});

/** What each kind did, using the markers the conversation already uses for these verbs. */
const ICONS = {
    reply: 'MailReply01Icon',
    like: 'FavouriteIcon',
    repost: 'ArrowReloadHorizontalIcon',
    rsvp: 'Calendar01Icon',
};

const icon = computed(() => ICONS[props.response.kind] ?? 'Link02Icon');

// One of mine is an ordinary internal link; anybody else's leaves the site.
const internal = computed(() => props.response.internal);

/**
 * The microformats property this link carries, which is what makes the post a
 * reply rather than a post that happens to contain a link.
 */
const property = computed(() => `u-${props.response.property}`);
</script>

<template>
    <!-- Built like a conversation byline: the marker, what I did, and the thing
         I did it to. Whatever the post says follows underneath. Inline flow, not
         flex, so a long title wraps as words rather than as one unbreakable box. -->
    <p class="text-sm text-neutral-500">
        <Icon :name="icon" class="mb-0.5 mr-1.5 inline size-3.5 align-middle" />
        <!-- An RSVP's answer is the verb, so it is the label, and the element
             carries the machine-readable value the wording spells out. -->
        <data v-if="response.rsvp" class="p-rsvp" :value="response.rsvp">{{ response.label }}</data>
        <span v-else>{{ response.label }}</span>
        {{ ' ' }}
        <component
            :is="internal ? Link : 'a'"
            :href="response.url"
            :rel="internal ? null : 'noopener noreferrer'"
            :class="[
                'h-cite rounded-sm font-medium text-neutral-700 underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                property,
            ]"
        >
            <img v-if="response.favicon" :src="response.favicon" alt="" loading="lazy" class="mb-0.5 mr-1.5 inline size-3.5 rounded-sm align-middle">
            <cite class="p-name not-italic">{{ response.title }}</cite>
        </component>

        <!-- Outside the link, because only the name is the p-name: the site is
             where it lives, not what it is called. -->
        <span v-if="response.host">{{ ' ' }}on {{ response.host }}</span>
    </p>
</template>
