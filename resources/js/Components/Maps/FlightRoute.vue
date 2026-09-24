<script setup>
import Eyebrow from '../Ui/Eyebrow.vue';
import Icon from '../Ui/Icon.vue';

defineProps({
    origin: { type: Object, required: true }, // { iata, city }
    destination: { type: Object, required: true }, // { iata, city }
    departTime: { type: String, default: null },
    arriveTime: { type: String, default: null },
    duration: { type: String, default: null },
    durationTitle: { type: String, default: null },
    note: { type: String, default: null },
    noteTitle: { type: String, default: null },
    compact: { type: Boolean, default: false },
});
</script>

<template>
    <div class="flex items-center justify-between gap-4">
        <div class="shrink-0">
            <div v-if="departTime" class="font-display tabular-nums" :class="compact ? 'text-lg font-bold leading-tight tracking-tight' : 'text-3xl font-extrabold leading-none tracking-tight'">{{ departTime }}</div>
            <div class="font-display" :class="compact ? 'text-sm' : 'text-lg font-bold leading-tight tracking-tight'">
                <abbr v-if="origin.name" :title="origin.name" class="cursor-help no-underline">{{ origin.iata }}</abbr>
                <template v-else>{{ origin.iata }}</template>
            </div>
            <div v-if="!compact && origin.city" class="text-xs text-neutral-500">{{ origin.city }}</div>
        </div>

        <div class="flex flex-1 flex-col items-center gap-1">
            <Eyebrow v-if="duration" :title="durationTitle" class="flex items-center gap-1 text-neutral-500 tabular-nums">
                <Icon v-if="durationTitle" name="Clock01Icon" class="size-3" />{{ duration }}
            </Eyebrow>
            <div class="relative flex w-full items-center justify-center">
                <span class="absolute inset-x-0 top-1/2 h-px -translate-y-1/2 bg-neutral-100" />
                <span class="relative bg-neutral-0 px-2 text-neutral-500">
                    <Icon name="Airplane01Icon" class="size-4" />
                </span>
            </div>
            <Eyebrow v-if="note" :title="noteTitle" class="flex items-center gap-1 text-neutral-500 tabular-nums">
                <Icon v-if="noteTitle" name="Route01Icon" class="size-3" />{{ note }}
            </Eyebrow>
        </div>

        <div class="shrink-0 text-right">
            <div v-if="arriveTime" class="font-display tabular-nums" :class="compact ? 'text-lg font-bold leading-tight tracking-tight' : 'text-3xl font-extrabold leading-none tracking-tight'">{{ arriveTime }}</div>
            <div class="font-display" :class="compact ? 'text-sm' : 'text-lg font-bold leading-tight tracking-tight'">
                <abbr v-if="destination.name" :title="destination.name" class="cursor-help no-underline">{{ destination.iata }}</abbr>
                <template v-else>{{ destination.iata }}</template>
            </div>
            <div v-if="!compact && destination.city" class="text-xs text-neutral-500">{{ destination.city }}</div>
        </div>
    </div>
</template>
