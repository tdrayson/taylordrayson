<script setup>
defineProps({
    // ConversationItems that linked here without saying anything threadable.
    mentions: { type: Array, default: () => [] },
});

const VERBS = {
    repost: 'reposted this',
    bookmark: 'bookmarked this',
    mention: 'mentioned this',
};
</script>

<template>
    <section v-if="mentions.length" aria-labelledby="mentions-heading">
        <h3 id="mentions-heading" class="text-label uppercase text-neutral-500">Elsewhere</h3>

        <ul class="mt-3 space-y-1">
            <li v-for="mention in mentions" :key="mention.id" class="h-cite text-meta text-neutral-700">
                <a
                    v-if="mention.authorUrl"
                    :href="mention.authorUrl"
                    rel="noopener noreferrer nofollow"
                    class="p-author rounded-sm font-medium text-neutral-900 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >{{ mention.authorName }}</a>
                <span v-else class="p-author font-medium text-neutral-900">{{ mention.authorName }}</span>

                {{ VERBS[mention.kind] ?? VERBS.mention }} on
                <a
                    :href="mention.sourceUrl"
                    rel="noopener noreferrer nofollow"
                    class="u-url rounded-sm underline decoration-neutral-100 underline-offset-2 transition-colors hover:text-accent-500 focus-visible:text-accent-500 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2"
                >
                    <time :datetime="mention.occurredAt.iso">{{ mention.occurredAt.label }}</time>
                </a>
            </li>
        </ul>
    </section>
</template>
