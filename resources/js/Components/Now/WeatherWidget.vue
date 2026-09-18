<script setup>
import { computed } from 'vue';
import { weatherFor } from '../../lib/weather.js';
import Icon from '../Ui/Icon.vue';
import Tooltip from '../Ui/Tooltip.vue';

const props = defineProps({
    condition: { type: String, default: 'partly-cloudy' },
    temp: { type: Number, default: null },
    // Humidity (%) and wind (mph) as the phone last reported them. Null until
    // a reading has been sent, in which case the pill simply does not render:
    // an invented number is worse than an absent one.
    humidity: { type: Number, default: null },
    wind: { type: Number, default: null },
});

// Icon, copy and gradient all come from the shared condition table, so the
// tile and the top bar can never disagree about the same sky.
const data = computed(() => weatherFor(props.condition));
const temp = computed(() => props.temp ?? data.value.t);

const pillClass = 'rounded bg-neutral-0/55 px-1.5 py-0.5 text-2xs font-semibold text-neutral-500 backdrop-blur-xs @5xs:rounded-sm @5xs:px-2 @5xs:text-xs @4xs:px-2.5 @4xs:text-base @xs:rounded-md @xs:px-3 @xs:py-1 @xs:text-xl @xs:backdrop-blur-sm';
</script>

<template>
    <div class="@container relative aspect-square overflow-hidden rounded-3xl bg-neutral-0 shadow-card">
        <div
            class="absolute -right-1/5 -bottom-1/5 z-1 size-43/50 animate-drift rounded-full opacity-35 blur-lg motion-reduce:animate-none @4xs:blur-xl"
            :style="{ background: data.gradient }"
        />
        <div class="relative z-2 flex h-full flex-col p-4 @5xs:p-5 @4xs:p-6 @xs:p-8">
            <p data-testid="weather-headline" class="text-base leading-tight font-extrabold tracking-tight text-neutral-900 @5xs:text-xl @4xs:text-2xl @xs:text-3xl">
                {{ data.line }}
            </p>
            <div class="mt-3 flex items-center gap-2 @5xs:mt-3.5 @5xs:gap-2.5 @4xs:mt-4 @4xs:gap-3 @xs:mt-5.5 @xs:gap-4">
                <Icon data-testid="weather-icon" class="size-5.5 shrink-0 text-neutral-500 @5xs:size-7 @4xs:size-8 @xs:size-11" :icon="data.icon" :stroke-width="1.8" />
                <span v-if="temp !== null" data-testid="weather-temp" class="text-xl font-semibold text-neutral-900 @5xs:text-2xl @4xs:text-3xl @xs:text-4xl">{{ temp }}°</span>
            </div>
            <!-- Bare readings, with the tooltip carrying what each one is. The
                 labels spelled out wrapped each pill onto two lines in a tile
                 this small. -->
            <div v-if="humidity !== null || wind !== null" class="mt-auto flex gap-1 @5xs:gap-1.5 @4xs:gap-2 @xs:gap-2.5">
                <Tooltip v-if="humidity !== null" :label="`${humidity}% humidity`" placement="top">
                    <span :class="pillClass">{{ humidity }}%</span>
                </Tooltip>
                <Tooltip v-if="wind !== null" :label="`${wind} mph wind`" placement="top">
                    <span :class="pillClass">{{ wind }} mph</span>
                </Tooltip>
            </div>
        </div>
    </div>
</template>
