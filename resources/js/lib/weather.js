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

// Reusable blob gradient families, used by the Now tile's aura.
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

/**
 * Every condition the phone can report: label, honest copy, Hugeicon and blob
 * gradient. One table, read by both the Now tile and the top bar, because two
 * separate maps drifted apart and showed different icons for the same sky.
 *
 * Keyed by the slug the server stores. Note the daytime pairs: Apple names the
 * same sky "Clear" after dark and "Sunny" in daylight, and Shortcuts hands over
 * whichever it is showing, so both spellings have to be here. `t` is a fallback
 * temperature for the gap before the first reading arrives; a real temp wins.
 */
export const CONDITIONS = {
    clear: { label: 'Clear', line: 'Clear skies. Make the most of it.', icon: Sun03Icon, t: 22, gradient: G.clear },
    sunny: { label: 'Sunny', line: 'Not a cloud in sight.', icon: Sun03Icon, t: 24, gradient: G.sunny },
    'mostly-clear': { label: 'Mostly Clear', line: 'Mostly clear. Mostly.', icon: SunCloud01Icon, t: 20, gradient: G.clear },
    'mostly-sunny': { label: 'Mostly Sunny', line: 'Sun winning, on balance.', icon: SunCloud01Icon, t: 23, gradient: G.sunny },
    'partly-cloudy': { label: 'Partly Cloudy', line: 'A bit of cloud, nothing dramatic.', icon: SunCloud02Icon, t: 18, gradient: G.grey },
    'partly-sunny': { label: 'Partly Sunny', line: 'Sun, between the gaps.', icon: SunCloud02Icon, t: 19, gradient: G.sunny },
    'mostly-cloudy': { label: 'Mostly Cloudy', line: 'More cloud than not.', icon: CloudIcon, t: 16, gradient: G.grey },
    cloudy: { label: 'Cloudy', line: 'Grey. Just grey.', icon: CloudyIcon, t: 16, gradient: G.grey },
    haze: { label: 'Haze', line: "Everything's a bit murky.", icon: HazeIcon, t: 19, gradient: G.tan },
    smoky: { label: 'Smoky', line: 'Air you can chew.', icon: HazeIcon, t: 20, gradient: G.tan },

    breezy: { label: 'Breezy', line: 'A pleasant little breeze.', icon: FastWindIcon, t: 17, gradient: G.teal },
    windy: { label: 'Windy', line: "It'll have your hat off.", icon: FastWindIcon, t: 14, gradient: G.teal },
    'blowing-dust': { label: 'Blowing Dust', line: 'Grit in your teeth weather.', icon: FastWindIcon, t: 24, gradient: G.tan },

    drizzle: { label: 'Drizzle', line: 'That annoying not-quite-rain.', icon: CloudDrizzleIcon, t: 11, gradient: G.blueGrey },
    rain: { label: 'Rain', line: 'Tipping it down, naturally.', icon: CloudRainIcon, t: 12, gradient: G.blueGrey },
    'heavy-rain': { label: 'Heavy Rain', line: 'Absolutely chucking it.', icon: CloudBigRainIcon, t: 11, gradient: G.darkBlue },
    'sun-showers': { label: 'Sun Showers', line: 'Sunny and raining. Pick one.', icon: SunCloudLittleRain01Icon, t: 16, gradient: G.sunRain },
    'freezing-drizzle': { label: 'Freezing Drizzle', line: 'Drizzle, but it bites.', icon: CloudDrizzleIcon, t: 1, gradient: G.ice },
    'freezing-rain': { label: 'Freezing Rain', line: 'Rain that turns to glass.', icon: CloudRainIcon, t: 0, gradient: G.ice },

    'isolated-thunderstorms': { label: 'Isolated Thunderstorms', line: 'The odd rumble about.', icon: SunCloudAngledRainZap01Icon, t: 19, gradient: G.purple },
    'scattered-thunderstorms': { label: 'Scattered Thunderstorms', line: 'Storms dotted around.', icon: CloudAngledRainZapIcon, t: 18, gradient: G.purple },
    thunderstorms: { label: 'Thunderstorms', line: 'Best stay inside for this.', icon: CloudLightningIcon, t: 18, gradient: G.purple },
    'strong-storms': { label: 'Strong Storms', line: 'This one means business.', icon: CloudLightningIcon, t: 17, gradient: G.dark },

    flurries: { label: 'Flurries', line: 'A few flakes drifting down.', icon: CloudLittleSnowIcon, t: 0, gradient: G.whiteBlue },
    snow: { label: 'Snow', line: 'Snow. Cold but pretty.', icon: CloudSnowIcon, t: -1, gradient: G.whiteBlue },
    'heavy-snow': { label: 'Heavy Snow', line: 'Properly dumping it down.', icon: CloudMidSnowIcon, t: -3, gradient: G.whiteBlue },
    'sun-flurries': { label: 'Sun Flurries', line: 'Snowing in the sunshine.', icon: SunCloudLittleSnow01Icon, t: 1, gradient: G.whiteBlue },
    sleet: { label: 'Sleet', line: "Snow's miserable cousin.", icon: CloudHailIcon, t: 1, gradient: G.iceGrey },
    hail: { label: 'Hail', line: 'Ice pellets from above.', icon: CloudHailstoneIcon, t: 4, gradient: G.iceGrey },
    'wintry-mix': { label: 'Wintry Mix', line: 'Everything at once. Lovely.', icon: CloudMidSnowIcon, t: 0, gradient: G.iceGrey },
    'blowing-snow': { label: 'Blowing Snow', line: 'Snow going sideways.', icon: CloudFastWindIcon, t: -4, gradient: G.whiteBlue },
    blizzard: { label: 'Blizzard', line: 'Do not go out in this.', icon: SnowIcon, t: -6, gradient: G.iceGrey },

    foggy: { label: 'Foggy', line: "Can't see a thing out there.", icon: CloudFogIcon, t: 9, gradient: G.grey },

    frigid: { label: 'Frigid', line: 'Bitterly cold. Wrap up.', icon: ThermometerColdIcon, t: -8, gradient: G.ice },
    hot: { label: 'Hot', line: 'Hot. Try not to melt.', icon: ThermometerWarmIcon, t: 34, gradient: G.hot },

    hurricane: { label: 'Hurricane', line: 'Board up the windows.', icon: Tornado01Icon, t: 24, gradient: G.dark },
    'tropical-storm': { label: 'Tropical Storm', line: 'Wind and water, lots of it.', icon: Tornado02Icon, t: 22, gradient: G.dark },
};

/**
 * A condition slug turned back into the words Apple used: "mostly-sunny" reads
 * as "Mostly Sunny". Only needed for a condition missing from the table, so an
 * unrecognised sky still gets a correct label rather than a raw slug.
 */
function labelFromSlug(condition) {
    return String(condition ?? '')
        .split('-')
        .filter(Boolean)
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1))
        .join(' ');
}

/**
 * The presentation for a condition slug.
 *
 * An unknown condition deliberately does NOT borrow another sky's copy. Falling
 * back to "partly cloudy" meant a condition we had never mapped read as a
 * confident, specific and wrong forecast, which is how this went unnoticed. It
 * keeps its real label and says nothing it cannot back up.
 */
export function weatherFor(condition) {
    const known = CONDITIONS[condition];

    if (known) {
        return known;
    }

    const label = labelFromSlug(condition);

    return {
        label: label || 'Unknown',
        line: label ? `${label}, apparently.` : 'No word from outside.',
        icon: CloudIcon,
        t: null,
        gradient: G.grey,
    };
}
