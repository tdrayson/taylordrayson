<script setup>
import { computed } from 'vue';
import { Link } from '@inertiajs/vue3';
import Icon from '../Ui/Icon.vue';

const props = defineProps({
    // One ResponseData: { kind, label, property, url, title, rsvp, rsvpLabel,
    // host, favicon, preview }.
    response: { type: Object, required: true },
    // One line in a feed card rather than the block an entry page carries.
    compact: { type: Boolean, default: false },
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
 * The microformats property this link carries, which is what makes the post a
 * reply rather than a post that happens to contain a link.
 */
const property = computed(() => `u-${props.response.property}`);
</script>

<template>
    <div :class="compact ? '' : 'max-w-prose'">
        <p class="flex items-center gap-1.5 text-neutral-500" :class="compact ? 'text-caption' : 'text-meta'">
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
            :class="[
                'h-cite flex items-center gap-2 rounded-lg border border-neutral-50 bg-neutral-25 transition-colors hover:border-neutral-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500',
                compact ? 'mt-1 max-w-sm px-2.5 py-1.5' : 'mt-1.5 px-3 py-2.5',
                property,
            ]"
        >
            <span
                v-if="internal"
                class="size-2 shrink-0 rounded-full"
                :style="{ backgroundColor: `var(--color-${preview.accent ?? preview.type}, var(--color-neutral-400))` }"
            />
            <img v-else-if="response.favicon" :src="response.favicon" alt="" loading="lazy" class="size-4 shrink-0 rounded-sm">

            <span class="min-w-0 flex-1">
                <span class="p-name block truncate font-medium text-neutral-900" :class="compact ? 'text-caption' : 'text-body'">{{ response.title }}</span>
                <!-- The second line is context, and a feed card has the whole
                     timeline for context already. -->
                <template v-if="! compact">
                    <span v-if="preview?.excerpt" class="block truncate text-caption text-neutral-500">{{ preview.excerpt }}</span>
                    <span v-else-if="response.host" class="block truncate text-caption text-neutral-500">{{ response.host }}</span>
                </template>
            </span>
        </component>
    </div>
</template>
