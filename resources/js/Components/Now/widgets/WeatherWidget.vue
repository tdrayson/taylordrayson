<script setup>
import { computed } from 'vue';
import {
    Sun03Icon,
    SunCloud01Icon,
    SunCloud02Icon,
    CloudIcon,
    CloudyIcon,
    HazeIcon,
    FastWindIcon,
    CloudDrizzleIcon,
    CloudRainIcon,
    CloudBigRainIcon,
    SunCloudLittleRain01Icon,
    SunCloudAngledRainZap01Icon,
    CloudAngledRainZapIcon,
    CloudLightningIcon,
    CloudLittleSnowIcon,
    CloudSnowIcon,
    CloudMidSnowIcon,
    SunCloudLittleSnow01Icon,
    CloudHailIcon,
    CloudHailstoneIcon,
    CloudFastWindIcon,
    SnowIcon,
    CloudFogIcon,
    ThermometerColdIcon,
    ThermometerWarmIcon,
    Tornado01Icon,
    Tornado02Icon,
} from '@hugeicons-pro/core-stroke-rounded';
import Icon from '../../Ui/Icon.vue';

const props = defineProps({
    condition: { type: String, default: 'partly-cloudy' },
    temp: { type: Number, default: null },
    high: { type: Number, default: null },
    low: { type: Number, default: null },
});

// Reusable blob gradient families.
const G = {
    clear: 'radial-gradient(circle at 32% 30%, #BFE6FF, #5AA9F5 54%, #2E7BE0)',
    sunny: 'radial-gradient(circle at 32% 30%, #FFE89A, #FFB347 54%, #FF8A2A)',
    hot: 'radial-gradient(circle at 32% 30%, #FFC83D, #FF6A1F 54%, #F5232B)',
    grey: 'radial-gradient(circle at 32% 30%, #E3E8EF, #A6B0BE 54%, #707A88)',
    blueGrey: 'radial-gradient(circle at 32% 30%, #B6CBE4, #5A7BA8 54%, #324C6E)',
    darkBlue: 'radial-gradient(circle at 32% 30%, #8FA8C8, #3E5C82 54%, #1E3354)',
    ice: 'radial-gradient(circle at 32% 30%, #BEE7FF, #4FA8F5 54%, #1E5AD0)',
    whiteBlue: 'radial-gradient(circle at 32% 30%, #FFFFFF, #CBE6FF 54%, #8FBEE8)',
    iceGrey: 'radial-gradient(circle at 32% 30%, #E8EEF5, #AFC2D6 54%, #7C93AC)',
    purple: 'radial-gradient(circle at 32% 30%, #C9B6F5, #7A54E0 54%, #3E1F8F)',
    teal: 'radial-gradient(circle at 32% 30%, #C2F4E4, #3FD6B6 54%, #0E9E84)',
    tan: 'radial-gradient(circle at 32% 30%, #EFE3CE, #C9AE86 54%, #997B4F)',
    dark: 'radial-gradient(circle at 32% 30%, #6B7280, #374151 54%, #15181E)',
    sunRain: 'radial-gradient(circle at 32% 30%, #FFE0A3, #79A7D8 54%, #3E6FA8)',
};

// Every Apple WeatherKit condition: honest copy, Hugeicon and blob gradient.
// Temps double as showcase defaults until real weather data is wired in.
const CONDITIONS = {
    clear: { line: 'Clear skies. Make the most of it.', icon: Sun03Icon, t: 22, hi: 24, lo: 15, gradient: G.clear },
    'mostly-clear': { line: 'Mostly clear. Mostly.', icon: SunCloud01Icon, t: 20, hi: 22, lo: 14, gradient: G.clear },
    'partly-cloudy': { line: 'A bit of cloud, nothing dramatic.', icon: SunCloud02Icon, t: 18, hi: 20, lo: 13, gradient: G.grey },
    'mostly-cloudy': { line: 'More cloud than not.', icon: CloudIcon, t: 16, hi: 18, lo: 12, gradient: G.grey },
    cloudy: { line: 'Grey. Just grey.', icon: CloudyIcon, t: 16, hi: 17, lo: 12, gradient: G.grey },
    haze: { line: "Everything's a bit murky.", icon: HazeIcon, t: 19, hi: 21, lo: 14, gradient: G.tan },
    smoky: { line: 'Air you can chew.', icon: HazeIcon, t: 20, hi: 22, lo: 15, gradient: G.tan },

    breezy: { line: 'A pleasant little breeze.', icon: FastWindIcon, t: 17, hi: 19, lo: 12, gradient: G.teal },
    windy: { line: "It'll have your hat off.", icon: FastWindIcon, t: 14, hi: 16, lo: 10, gradient: G.teal },
    'blowing-dust': { line: 'Grit in your teeth weather.', icon: FastWindIcon, t: 24, hi: 27, lo: 18, gradient: G.tan },

    drizzle: { line: 'That annoying not-quite-rain.', icon: CloudDrizzleIcon, t: 11, hi: 13, lo: 8, gradient: G.blueGrey },
    rain: { line: 'Tipping it down, naturally.', icon: CloudRainIcon, t: 12, hi: 14, lo: 9, gradient: G.blueGrey },
    'heavy-rain': { line: 'Absolutely chucking it.', icon: CloudBigRainIcon, t: 11, hi: 13, lo: 8, gradient: G.darkBlue },
    'sun-showers': { line: 'Sunny and raining. Pick one.', icon: SunCloudLittleRain01Icon, t: 16, hi: 18, lo: 12, gradient: G.sunRain },
    'freezing-drizzle': { line: 'Drizzle, but it bites.', icon: CloudDrizzleIcon, t: 1, hi: 2, lo: -2, gradient: G.ice },
    'freezing-rain': { line: 'Rain that turns to glass.', icon: CloudRainIcon, t: 0, hi: 2, lo: -3, gradient: G.ice },

    'isolated-thunderstorms': { line: 'The odd rumble about.', icon: SunCloudAngledRainZap01Icon, t: 19, hi: 22, lo: 15, gradient: G.purple },
    'scattered-thunderstorms': { line: 'Storms dotted around.', icon: CloudAngledRainZapIcon, t: 18, hi: 21, lo: 14, gradient: G.purple },
    thunderstorms: { line: 'Best stay inside for this.', icon: CloudLightningIcon, t: 18, hi: 20, lo: 15, gradient: G.purple },
    'strong-storms': { line: 'This one means business.', icon: CloudLightningIcon, t: 17, hi: 20, lo: 13, gradient: G.dark },

    flurries: { line: 'A few flakes drifting down.', icon: CloudLittleSnowIcon, t: 0, hi: 2, lo: -3, gradient: G.whiteBlue },
    snow: { line: 'Snow. Cold but pretty.', icon: CloudSnowIcon, t: -1, hi: 1, lo: -4, gradient: G.whiteBlue },
    'heavy-snow': { line: 'Properly dumping it down.', icon: CloudMidSnowIcon, t: -3, hi: -1, lo: -7, gradient: G.whiteBlue },
    'sun-flurries': { line: 'Snowing in the sunshine.', icon: SunCloudLittleSnow01Icon, t: 1, hi: 3, lo: -2, gradient: G.whiteBlue },
    sleet: { line: "Snow's miserable cousin.", icon: CloudHailIcon, t: 1, hi: 2, lo: -2, gradient: G.iceGrey },
    hail: { line: 'Ice pellets from above.', icon: CloudHailstoneIcon, t: 4, hi: 6, lo: 1, gradient: G.iceGrey },
    'wintry-mix': { line: 'Everything at once. Lovely.', icon: CloudMidSnowIcon, t: 0, hi: 2, lo: -3, gradient: G.iceGrey },
    'blowing-snow': { line: 'Snow going sideways.', icon: CloudFastWindIcon, t: -4, hi: -2, lo: -9, gradient: G.whiteBlue },
    blizzard: { line: 'Do not go out in this.', icon: SnowIcon, t: -6, hi: -3, lo: -12, gradient: G.iceGrey },

    foggy: { line: "Can't see a thing out there.", icon: CloudFogIcon, t: 9, hi: 11, lo: 7, gradient: G.grey },

    frigid: { line: 'Bitterly cold. Wrap up.', icon: ThermometerColdIcon, t: -8, hi: -5, lo: -14, gradient: G.ice },
    hot: { line: 'Hot. Try not to melt.', icon: ThermometerWarmIcon, t: 34, hi: 36, lo: 27, gradient: G.hot },

    hurricane: { line: 'Board up the windows.', icon: Tornado01Icon, t: 24, hi: 27, lo: 21, gradient: G.dark },
    'tropical-storm': { line: 'Wind and water, lots of it.', icon: Tornado02Icon, t: 22, hi: 25, lo: 19, gradient: G.dark },
};

const data = computed(() => CONDITIONS[props.condition] ?? CONDITIONS['partly-cloudy']);
const temp = computed(() => props.temp ?? data.value.t);
const high = computed(() => props.high ?? data.value.hi);
const low = computed(() => props.low ?? data.value.lo);
</script>

<template>
    <div class="weather relative aspect-square overflow-hidden rounded-3xl bg-neutral-0 shadow-card">
        <div class="weather__aura" :style="{ background: data.gradient }" />
        <div class="weather__inner">
            <p class="weather__headline">{{ data.line }}</p>
            <div class="weather__current">
                <Icon class="weather__icon" :icon="data.icon" :stroke-width="1.8" />
                <span class="weather__temp">{{ temp }}°</span>
            </div>
            <div class="weather__range">
                <span class="weather__pill">H {{ high }}°</span>
                <span class="weather__pill">L {{ low }}°</span>
            </div>
        </div>
    </div>
</template>

<style scoped>
/* The card is the query container; inner sizing is in cqw (1cqw ≈ reference
   px ÷ 2.48) so the whole composition scales with the grid cell. */
.weather {
    container-type: inline-size;
}

/* Soft corner aura: a blurred glow that bleeds from the bottom-right with no
   defined edge, rather than a crisp circle. */
.weather__aura {
    position: absolute;
    right: -20cqw;
    bottom: -20cqw;
    width: 86cqw;
    height: 86cqw;
    border-radius: 50%;
    z-index: 1;
    filter: blur(10cqw);
    opacity: 0.35;
    animation: weather-float 6.5s ease-in-out infinite;
}

.weather__inner {
    position: relative;
    z-index: 2;
    display: flex;
    height: 100%;
    flex-direction: column;
    padding: 10.5cqw;
}

.weather__headline {
    font-size: 10cqw;
    font-weight: 800;
    line-height: 1.12;
    letter-spacing: -0.02em;
    color: var(--color-neutral-900);
}

.weather__current {
    display: flex;
    align-items: center;
    gap: 5cqw;
    margin-top: 7cqw;
}

.weather__icon {
    width: 13.7cqw;
    height: 13.7cqw;
    flex: none;
    color: var(--color-neutral-500);
}

.weather__temp {
    font-size: 12cqw;
    font-weight: 600;
    letter-spacing: -0.01em;
    color: var(--color-neutral-900);
}

.weather__range {
    margin-top: auto;
    display: flex;
    gap: 3cqw;
}

.weather__pill {
    padding: 1.2cqw 4cqw;
    border-radius: 3.6cqw;
    font-size: 6.5cqw;
    font-weight: 600;
    color: #5e646c;
    background: rgba(255, 255, 255, 0.55);
    backdrop-filter: blur(2.5cqw);
    -webkit-backdrop-filter: blur(2.5cqw);
}

@keyframes weather-float {
    0%,
    100% {
        transform: translate(0, 0);
    }

    50% {
        transform: translate(-2.8cqw, -2.8cqw);
    }
}

@media (prefers-reduced-motion: reduce) {
    .weather__aura {
        animation: none;
    }
}
</style>
