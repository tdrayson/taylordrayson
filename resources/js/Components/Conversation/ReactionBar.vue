<script setup>
import { ref } from 'vue';
import { cn } from '../../lib/cn.js';

const props = defineProps({
    // [{ key, emoji, label, count, mine }] — every offered emoji, plus any that
    // arrived by webmention and matches none of them.
    reactions: { type: Array, default: () => [] },
    type: { type: String, required: true },
    id: { type: Number, required: true },
});

const buckets = ref([...props.reactions]);
const busy = ref(null);
const failed = ref(false);

// Buckets whose key is an emoji came in by webmention and belong to whoever
// sent them, so there is nothing here to toggle.
const isOurs = (bucket) => /^[a-z]+$/.test(bucket.key);

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
    }
}
</script>

<template>
    <div>
        <ul class="flex flex-wrap gap-2">
            <li v-for="bucket in buckets" :key="bucket.key">
                <button
                    type="button"
                    :disabled="! isOurs(bucket) || busy === bucket.key"
                    :aria-pressed="isOurs(bucket) ? bucket.mine : undefined"
                    :aria-label="isOurs(bucket)
                        ? `${bucket.label}, ${bucket.count} so far`
                        : `${bucket.count} reacted ${bucket.emoji} from elsewhere`"
                    :title="bucket.label"
                    :class="cn(
                        'inline-flex min-h-9 items-center gap-1.5 rounded-full border px-3 text-meta transition-colors',
                        'focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-accent-500 focus-visible:ring-offset-2',
                        bucket.mine
                            ? 'border-accent-500 bg-accent-50 text-accent-700'
                            : 'border-neutral-100 text-neutral-700',
                        isOurs(bucket) ? 'hover:border-accent-500 hover:bg-accent-50' : 'cursor-default',
                        busy === bucket.key && 'opacity-50',
                    )"
                    @click="toggle(bucket)"
                >
                    <span aria-hidden="true">{{ bucket.emoji }}</span>
                    <span v-if="bucket.count" class="tabular-nums font-medium">{{ bucket.count }}</span>
                </button>
            </li>
        </ul>

        <p v-if="failed" class="mt-2 text-caption text-red-600">
            That did not save. Try again in a moment.
        </p>
    </div>
</template>
