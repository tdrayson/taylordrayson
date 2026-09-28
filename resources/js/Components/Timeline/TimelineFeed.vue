<script setup>
import { computed } from 'vue';
import FeedItem from './FeedItem.vue';
import FeedRail from './FeedRail.vue';
import { provideLinkContext } from '../../lib/linkContext.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
    // The day these cards sit under, when they take part in a view transition.
    // Part of each name, since a multi-day entry shows under more than one day.
    transitionScope: { type: String, default: null },
});

// Every feed surface renders through here, so the link data the note cards
// carry is merged and provided once, the same contract BlockContent reads on an
// entry page. The maps are keyed by href and host, so two notes linking to the
// same thing share one entry.
const links = computed(() => ({
    previews: Object.assign({}, ...props.items.map((item) => item.previews ?? {})),
    favicons: Object.assign({}, ...props.items.map((item) => item.favicons ?? {})),
}));

provideLinkContext(links);

// Those two are context rather than props, and left in the spread they would
// land on the card's root element as attributes.
const cards = computed(() => props.items.map(({ previews, favicons, ...card }) => card));

/** The card's view-transition style, or nothing outside a transition scope. */
function transitionStyle(card, index) {
    if (! props.transitionScope) {
        return undefined;
    }

    const key = card.id === null || card.id === undefined ? `i${index}` : `${card.iconKey}-${card.id}`;

    return { viewTransitionName: `entry-${props.transitionScope}-${key}`, viewTransitionClass: 'timeline-item' };
}
</script>

<template>
    <FeedRail>
        <FeedItem
            v-for="(card, index) in cards"
            :key="index"
            v-bind="card"
            :style="transitionStyle(card, index)"
        />
    </FeedRail>
</template>
