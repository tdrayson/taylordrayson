<script setup>
import FlightMapListItem from './FlightMapListItem.vue';

/**
 * The flight globe map's flight list: the accessible, keyboard-operable
 * counterpart to the globe's arcs.
 * @param {Array} entries FlightMapEntry payloads.
 * @param {number|null} selectedId Currently selected flight id.
 */
defineProps({
    entries: { type: Array, default: () => [] },
    selectedId: { type: Number, default: null },
});

defineEmits(['select', 'hover']);
</script>

<template>
    <ul class="divide-y divide-neutral-50">
        <li v-for="entry in entries" :key="entry.id">
            <FlightMapListItem
                :entry="entry"
                :selected="entry.id === selectedId"
                @select="$emit('select', $event)"
                @hover="$emit('hover', $event)"
            />
        </li>
    </ul>
</template>
