<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ResponseData: { kind, label, property, url, rsvp, rsvpLabel, host,
    // favicon, preview }.
    response: { type: Object, required: true },
});

/** What each kind did, matching the markers the conversation uses for the same verbs. */
const ICONS = {
    reply: 'MailReply01Icon',
    like: 'FavouriteIcon',
    repost: 'RepeatIcon',
    rsvp: 'Calendar01Icon',
};

const icon = computed(() => ICONS[props.response.kind] ?? 'Link02Icon');

// One of mine resolves to a card; anybody else's is only ever a URL on a host.
const preview = computed(() => props.response.preview ?? null);
const internal = computed(() => preview.value !== null);

/**
 * What to call the target.
 *
 * A post of mine has a real title. For anybody else's the URL is all we know,
 * so it is shown the way a browser writes it, without the scheme that no one
 * reads.
 */
const title = computed(() => preview.value?.title ?? props.response.url.replace(/^https?:\/\//, ''));

/**
 * The microformats property this link carries, which is what makes the post a
 * reply rather than a post that happens to contain a link.
 */
const property = computed(() => `u-${props.response.property}`);
</script>

<template>
    <div class="max-w-prose">
        <p class="flex items-center gap-1.5 text-meta text-neutral-500">
            <Icon :name="icon" class="size-3.5" />
            <span>{{ response.label }}</span>
            <!-- The answer is the point of an RSVP, so it is said in the label
                 rather than left for the reader to infer from the target. -->
            <template v-if="response.rsvpLabel">
                <span aria-hidden="true">,</span>
                <data class="p-rsvp font-medium text-neutral-700" :value="response.rsvp">{{ response.rsvpLabel }}</data>
            </template>
        </p>

        <component
            :is="internal ? Link : 'a'"
            :href="response.url"
            :rel="internal ? null : 'noopener'"
            :class="['h-cite mt-1.5 flex items-center gap-2 rounded-lg border border-neutral-50 bg-neutral-25 px-3 py-2.5 transition-colors hover:border-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500', property]"
        >
            <span
                v-if="internal"
                class="size-2 shrink-0 rounded-full"
                :style="{ backgroundColor: `var(--color-${preview.accent ?? preview.type}, var(--color-neutral-400))` }"
            />
            <img v-else-if="response.favicon" :src="response.favicon" alt="" loading="lazy" class="size-4 shrink-0 rounded-sm">

            <span class="min-w-0 flex-1">
                <span class="block truncate text-body font-medium text-neutral-900 p-name">{{ title }}</span>
                <span v-if="preview?.excerpt" class="block truncate text-caption text-neutral-500">{{ preview.excerpt }}</span>
                <span v-else-if="response.host" class="block truncate text-caption text-neutral-500">{{ response.host }}</span>
            </span>
        </component>
    </div>
</template>
