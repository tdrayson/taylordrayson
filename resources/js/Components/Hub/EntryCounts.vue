<script setup>
import { computed, ref } from 'vue';
import { router } from '@inertiajs/vue3';
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

/** How long a queued row keeps its tick before it offers to sync again. */
const TICK_MS = 4000;

/** Types queued in the last few seconds, each on its own timer. */
const queued = ref([]);

const isQueued = (type) => queued.value.includes(type.type);

/** Pull this type now rather than wait for its slot on the schedule. */
function sync(type) {
    router.post(`/hq/sync/${type.type}`, {}, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            queued.value = [...queued.value, type.type];
            setTimeout(() => {
                queued.value = queued.value.filter((key) => key !== type.type);
            }, TICK_MS);
        },
    });
}

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
                    :key="type.type"
                    class="flex items-baseline gap-3 border-b border-neutral-50 py-2 text-sm last:border-0"
                >
                    <Icon :icon="type.icon" class="size-4 flex-none translate-y-0.5 text-neutral-400" />
                    <span class="min-w-0 flex-1 truncate text-neutral-700">{{ type.label }}</span>
                    <span class="w-16 shrink-0 text-right text-xs tabular-nums text-neutral-700">{{ number(type.count) }}</span>
                    <span class="w-28 shrink-0 text-right text-xs text-neutral-500">{{ type.lag }}</span>

                    <!-- Held open on every row, so both groups' columns line up. -->
                    <span class="-my-0.5 flex w-6 shrink-0 justify-end self-center">
                        <button
                            v-if="type.syncable"
                            type="button"
                            class="flex size-6 items-center justify-center rounded-full text-neutral-400 transition-colors hover:bg-neutral-25 hover:text-accent-500 focus-visible:text-accent-500 disabled:text-green-600 disabled:hover:bg-transparent"
                            :disabled="isQueued(type)"
                            :aria-label="isQueued(type) ? `${type.label} sync queued` : `Sync ${type.label} now`"
                            :title="isQueued(type) ? 'Queued' : 'Sync now'"
                            @click="sync(type)"
                        >
                            <Icon :name="isQueued(type) ? 'Tick02Icon' : 'RefreshIcon'" class="size-4" />
                        </button>
                    </span>
                </li>
            </ul>
        </div>
    </div>
</template>
