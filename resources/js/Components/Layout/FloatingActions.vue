<script setup>
import { computed } from 'vue';
import BackToTop from './BackToTop.vue';
import QuickAdd from './QuickAdd.vue';
import { player } from '../../lib/player.js';

/**
 * The bottom-right stack. Owning the edge offset here is what keeps back-to-top
 * above the add button whether or not that button is drawn: signed out, the
 * column simply has one row in it.
 */
defineProps({
    // Writing screens carry their own actions on this edge and a sticky save
    // bar along the bottom, so the stack stays off them entirely.
    minimal: { type: Boolean, default: false },
});

// Clears the audio bar, which is fixed along the bottom on the same edge.
const playing = computed(() => player.mode === 'audio' && Boolean(player.track));
</script>

<template>
    <div
        v-if="! minimal"
        class="fixed right-5 z-40 flex flex-col items-end gap-3 print:hidden"
        :class="playing ? 'bottom-28' : 'bottom-6'"
    >
        <BackToTop />
        <QuickAdd />
    </div>
</template>
