<script setup>
import { computed } from 'vue';
import StatRow from '../Stats/StatRow.vue';
import DetailList from '../Ui/DetailList.vue';
import { number } from '../../lib/format.js';

const props = defineProps({
    entry: { type: Object, required: true },
});

const stats = computed(() => [
    { label: 'Volume', value: number(props.entry.litres, 1), unit: 'L' },
    { label: 'Cost', value: props.entry.cost ? `£${number(props.entry.cost, 2)}` : null },
    { label: 'Per litre', value: props.entry.price_per_litre ? `£${number(props.entry.price_per_litre, 3)}` : null },
    { label: 'Odometer', value: number(props.entry.odometer), unit: 'mi' },
]);

const rows = computed(() => [
    { label: 'City', value: props.entry.city },
    { label: 'Fuel card cost', value: props.entry.fuel_card_cost ? `£${number(props.entry.fuel_card_cost, 2)}` : null },
]);
</script>

<template>
    <div class="space-y-8">
        <StatRow :stats="stats" />
        <DetailList :rows="rows" />
    </div>
</template>
