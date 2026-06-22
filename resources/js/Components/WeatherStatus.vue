<script setup>
import { computed } from 'vue';
import {
    CloudIcon,
    CloudyIcon,
    SunCloud01Icon,
    SunCloud02Icon,
    Sun03Icon,
    CloudFogIcon,
    CloudDrizzleIcon,
    CloudRainIcon,
    CloudBigRainIcon,
    CloudSunRainIcon,
    CloudLightningIcon,
    CloudHailIcon,
    CloudHailstoneIcon,
    CloudLittleSnowIcon,
    CloudMidSnowIcon,
    CloudSnowIcon,
    SunCloudLittleSnowIcon,
    FastWindIcon,
    CloudSlowWindIcon,
    TemperatureIcon,
    SnowIcon,
    Tornado01Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import Icon from './Icon.vue';

const props = defineProps({
    temp: { type: String, default: '25°C' },
    condition: { type: String, default: 'Partly Cloudy' },
    compact: { type: Boolean, default: false },
});

// Apple WeatherKit conditions → icon. Ordered most-specific first; matched against
// the condition with non-letters stripped, so "Partly Cloudy" and "PartlyCloudy" both work.
const conditionIcons = [
    ['hurricane', Tornado01Icon],
    ['tropical', Tornado01Icon],
    ['blizzard', CloudSnowIcon],
    ['thunder', CloudLightningIcon],
    ['storm', CloudLightningIcon],
    ['freezing', CloudHailstoneIcon],
    ['sleet', CloudHailstoneIcon],
    ['hail', CloudHailIcon],
    ['sunflurr', SunCloudLittleSnowIcon],
    ['flurr', CloudLittleSnowIcon],
    ['wintry', CloudMidSnowIcon],
    ['snow', CloudSnowIcon],
    ['shower', CloudSunRainIcon],
    ['heavyrain', CloudBigRainIcon],
    ['drizzle', CloudDrizzleIcon],
    ['rain', CloudRainIcon],
    ['dust', FastWindIcon],
    ['smok', CloudFogIcon],
    ['haze', CloudFogIcon],
    ['fog', CloudFogIcon],
    ['breez', CloudSlowWindIcon],
    ['wind', FastWindIcon],
    ['hot', TemperatureIcon],
    ['frigid', SnowIcon],
    ['partly', SunCloud02Icon],
    ['mostlyclear', SunCloud01Icon],
    ['mostlycloud', CloudyIcon],
    ['overcast', CloudyIcon],
    ['cloud', CloudIcon],
    ['clear', Sun03Icon],
    ['sun', Sun03Icon],
];

const icon = computed(() => {
    const normalized = props.condition.toLowerCase().replace(/[^a-z]/g, '');
    const match = conditionIcons.find(([key]) => normalized.includes(key));

    return match ? match[1] : CloudIcon;
});
</script>

<template>
    <span class="inline-flex items-center gap-1.5">
        <Icon :icon="icon" :class="['text-ink-3', compact ? 'size-3.5' : 'size-4']" />
        <span class="tnum">{{ temp }}</span>
    </span>
</template>
