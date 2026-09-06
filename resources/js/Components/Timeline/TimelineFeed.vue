<script setup>
import { computed } from 'vue';
import FeedItem from './FeedItem.vue';
import FeedRail from './FeedRail.vue';
import { provideLinkContext } from '../../lib/linkContext.js';

const props = defineProps({
    items: { type: Array, default: () => [] },
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
</script>

<template>
    <FeedRail>
        <FeedItem
            v-for="(card, index) in cards"
            :key="index"
            v-bind="card"
        />
    </FeedRail>
</template>
