<script setup>
import { computed } from 'vue';
import { Link, router, setLayoutProps } from '@inertiajs/vue3';
import AppHead from '../Components/AppHead.vue';
import AppLayout from '../Layouts/AppLayout.vue';
import Button from '../Components/Ui/Button.vue';

defineOptions({ layout: AppLayout, inheritAttrs: false });

const props = defineProps({
    // { comments: [...], mentions: [...] } — everything waiting to be read.
    pending: { type: Object, default: () => ({ comments: [], mentions: [] }) },
    // { comments: [...] } — caught by the filter, kept so a miss can be released.
    spam: { type: Object, default: () => ({ comments: [] }) },
});

const waiting = computed(() => [...props.pending.comments, ...props.pending.mentions]);

setLayoutProps({ breadcrumb: [{ label: 'Moderation' }] });

/** Approve, spam or delete, then reload so the row leaves the list. */
function act(item, action) {
    router.post(`/moderation/${item.kind}/${item.id}/${action}`, {}, { preserveScroll: true });
}
</script>

<template>
    <AppHead :og="{ title: 'Moderation' }" />

    <div class="max-w-2xl">
        <h1 class="font-display text-display">Moderation</h1>

        <section class="mt-8">
            <h2 class="text-label uppercase text-neutral-500">
                Waiting ({{ waiting.length }})
            </h2>

            <p v-if="! waiting.length" class="mt-3 text-meta text-neutral-500">
                Nothing to read.
            </p>

            <ul v-else class="mt-3 space-y-4">
                <li v-for="item in waiting" :key="`${item.kind}-${item.id}`" class="rounded-lg border border-neutral-100 p-4">
                    <p class="flex flex-wrap items-baseline gap-x-2 text-meta">
                        <span class="font-semibold text-neutral-900">{{ item.author }}</span>
                        <span class="text-caption text-neutral-500">{{ item.received }}</span>
                        <span v-if="item.kind === 'mention'" class="text-caption uppercase text-neutral-500">Webmention</span>
                    </p>

                    <p v-if="item.email" class="text-caption text-neutral-500">{{ item.email }}</p>

                    <p v-if="item.body" class="mt-2 whitespace-pre-line text-body text-neutral-900">{{ item.body }}</p>

                    <p class="mt-2 text-caption text-neutral-500">
                        On <Link v-if="item.on" :href="item.on" class="underline underline-offset-2 hover:text-accent-500">{{ item.on }}</Link>
                        <span v-else>an entry that has since gone</span>
                        <template v-if="item.sourceUrl">,
                            from
                            <a :href="item.sourceUrl" rel="noopener noreferrer nofollow" class="underline underline-offset-2 hover:text-accent-500">{{ item.sourceUrl }}</a>
                        </template>
                    </p>

                    <div class="mt-3 flex flex-wrap gap-2">
                        <Button size="sm" variant="primary" @click="act(item, 'approve')">Approve</Button>
                        <Button size="sm" @click="act(item, 'spam')">Spam</Button>
                        <Button size="sm" variant="ghost" @click="act(item, 'delete')">Delete</Button>
                    </div>
                </li>
            </ul>
        </section>

        <!-- Last and collapsed by default: this is a list to glance at for a
             mistake, not one to work through. -->
        <details v-if="spam.comments.length" class="mt-10">
            <summary class="cursor-pointer text-label uppercase text-neutral-500">
                Spam ({{ spam.comments.length }})
            </summary>

            <ul class="mt-3 space-y-3">
                <li v-for="item in spam.comments" :key="item.id" class="rounded-lg border border-neutral-50 p-3">
                    <p class="text-meta font-semibold text-neutral-900">{{ item.author }}</p>
                    <p class="mt-1 line-clamp-3 whitespace-pre-line text-caption text-neutral-500">{{ item.body }}</p>

                    <div class="mt-2 flex gap-2">
                        <Button size="sm" @click="act(item, 'approve')">Not spam</Button>
                        <Button size="sm" variant="ghost" @click="act(item, 'delete')">Delete</Button>
                    </div>
                </li>
            </ul>
        </details>
    </div>
</template>
