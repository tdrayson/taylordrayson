<script setup>
import { computed } from 'vue';

const props = defineProps({
    entries: { type: Array, default: () => [] },
    highlightFp: { type: String, default: '' },
    showHeading: { type: Boolean, default: true },
});

// Names that appear more than once need their fingerprint shown to tell them apart.
const duplicateNames = computed(() => {
    const counts = {};

    for (const entry of props.entries) {
        const key = entry.name.trim().toLowerCase();
        counts[key] = (counts[key] ?? 0) + 1;
    }

    return counts;
});

function isYou(entry) {
    return props.highlightFp !== '' && entry.fp === props.highlightFp;
}

function isDuplicate(entry) {
    return (duplicateNames.value[entry.name.trim().toLowerCase()] ?? 0) > 1;
}
</script>

<template>
    <div>
        <p v-if="showHeading" class="text-eyebrow uppercase text-ink-3">Leaderboard</p>

        <ol v-if="entries.length" :class="showHeading ? 'mt-3' : ''">
            <li
                v-for="(entry, index) in entries"
                :key="`${entry.fp}-${index}`"
                class="flex items-center gap-3 border-b border-line-2 py-2 last:border-b-0"
            >
                <span class="tnum w-6 text-meta" :class="isYou(entry) ? 'text-accent' : 'text-ink-3'">{{ index + 1 }}</span>
                <span class="min-w-0 flex-1 truncate text-body" :class="isYou(entry) ? 'font-semibold text-accent' : 'text-ink'">
                    {{ entry.name }}<span v-if="isDuplicate(entry)" class="ml-1 text-meta text-ink-3">#{{ entry.fp }}</span>
                    <span v-if="isYou(entry)" class="ml-2 inline-block rounded bg-surface px-1.5 py-0.5 align-middle text-label uppercase text-ink-3">you</span>
                </span>
                <span v-if="entry.date" class="hidden text-meta text-ink-3 sm:block">{{ entry.date }}</span>
                <span class="tnum w-12 text-right text-body font-semibold" :class="isYou(entry) ? 'text-accent' : 'text-ink'">{{ entry.score }}</span>
            </li>
        </ol>

        <p v-else class="mt-3 text-meta text-ink-3">No scores yet. Be the first to log one.</p>
    </div>
</template>
