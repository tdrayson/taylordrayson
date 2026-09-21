<script setup>
import { computed } from 'vue';
import Eyebrow from '../Ui/Eyebrow.vue';
import Icon from '../Ui/Icon.vue';
import { number } from '../../lib/format.js';

/**
 * Every data type, how much of it there is, and when it last got something.
 *
 * Split by how entries arrive rather than judged: a failing sync already pushes
 * to the phone through FailureAlert, so a health verdict here would be a rival
 * to the one that works.
 */
const props = defineProps({
    types: { type: Array, default: () => [] },
});

const synced = computed(() => props.types.filter((type) => type.synced));
const byHand = computed(() => props.types.filter((type) => ! type.synced));

const total = (rows) => rows.reduce((sum, type) => sum + type.count, 0);

const summary = computed(
    () => `${number(total(synced.value))} synced, ${number(total(byHand.value))} added by hand`,
);
</script>

<template>
    <div>
        <p class="text-sm text-neutral-500">{{ summary }}</p>

        <div
            v-for="group in [
                { label: 'Synced', rows: synced },
                { label: 'By hand', rows: byHand },
            ]"
            :key="group.label"
            class="mt-5"
        >
            <Eyebrow v-if="group.rows.length" as="h3" class="text-neutral-400">{{ group.label }}</Eyebrow>

            <ul class="mt-1">
                <li
                    v-for="type in group.rows"
                    :key="type.label"
                    class="flex items-baseline gap-3 border-b border-neutral-50 py-2 text-sm last:border-0"
                >
                    <Icon :icon="type.icon" class="size-4 flex-none translate-y-0.5 text-neutral-400" />
                    <span class="min-w-0 flex-1 truncate text-neutral-700">{{ type.label }}</span>
                    <span class="w-16 shrink-0 text-right text-xs tabular-nums text-neutral-700">{{ number(type.count) }}</span>
                    <span class="w-28 shrink-0 text-right text-xs text-neutral-500">{{ type.lag }}</span>
                </li>
            </ul>
        </div>
    </div>
</template>
