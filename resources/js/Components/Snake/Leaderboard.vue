<script setup>
import Eyebrow from '../Ui/Eyebrow.vue';
import { relativeDay } from '../../lib/format.js';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    highlightFp: { type: String, default: '' },
    showHeading: { type: Boolean, default: true },
});

// Full date once a score is older than the shared relative window, since a
// leaderboard spans years and "3 May" alone would be ambiguous.
function scoreDate(iso) {
    return relativeDay(iso) ?? new Date(iso).toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
}

// The fingerprint is backend-only: it never renders, it just lets a player spot
// their own row. Duplicate names are left as-is (two Jakes are simply two Jakes).
function isYou(entry) {
    return props.highlightFp !== '' && entry.fp === props.highlightFp;
}
</script>

<template>
    <div>
        <Eyebrow v-if="showHeading" as="p" class="text-neutral-500">Leaderboard</Eyebrow>

        <ol v-if="entries.length" :class="showHeading ? 'mt-3' : ''">
            <li
                v-for="(entry, index) in entries"
                :key="`${entry.fp}-${index}`"
                class="flex items-center gap-3 border-b border-neutral-50 py-2 last:border-b-0"
            >
                <span class="tabular-nums w-6 text-sm" :class="isYou(entry) ? 'text-accent-500' : 'text-neutral-500'">{{ index + 1 }}</span>
                <span class="min-w-0 flex-1 truncate text-base" :class="isYou(entry) ? 'font-semibold text-accent-500' : 'text-neutral-900'">
                    {{ entry.name }}
                    <Eyebrow v-if="isYou(entry)" as="span" class="ml-2 inline-block rounded bg-neutral-25 px-1.5 py-0.5 align-middle text-neutral-500">you</Eyebrow>
                </span>
                <span v-if="entry.date" class="hidden text-sm text-neutral-500 sm:block">{{ scoreDate(entry.date) }}</span>
                <span class="tabular-nums w-12 text-right text-base font-semibold" :class="isYou(entry) ? 'text-accent-500' : 'text-neutral-900'">{{ entry.score }}</span>
            </li>
        </ol>

        <p v-else class="mt-3 text-sm text-neutral-500">No scores yet. Be the first to log one.</p>
    </div>
</template>
