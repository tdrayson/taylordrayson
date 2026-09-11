import { weatherFor } from './weather.js';
import { entryType } from '../entryTypes.js';

/** Shared inline sizing so a tag's icon sits on the text baseline without disturbing line height. */
export const DYNAMIC_TAG_ICON_CLASS = 'mb-0.5 mr-1 inline size-3.5 align-middle';

/**
 * The host a URL points at, lowercase and without a leading www. Mirrors
 * PortableTextBlocks.js's own `hostOf`, kept separate so this module has no
 * dependency on the renderer.
 */
function hostOf(href) {
    try {
        return new URL(href).hostname.toLowerCase().replace(/^www\./, '');
    } catch {
        return null;
    }
}

/**
 * The icon a resolved dynamic tag should show beside its value, described
 * generically so both the published render (PortableTextBlocks.js) and the
 * editor chip (DynamicTagChip.vue) draw the exact same glyph without either
 * one owning the mapping. Each branch reuses the source the rest of the app
 * already draws that glyph from: `weatherFor` for the sky, `entryType` for a
 * timeline type, `BatteryStatus`'s own colour logic for the charge level, and
 * the favicon map external links already use for a social profile.
 *
 * @param {string} tagName The dotted tag name, e.g. `ambient.weather.condition`.
 * @param {*} value The tag's resolved value, as carried on `dynamicTag.value`.
 * @param {*} icon The tag's icon payload, as carried on `dynamicTag.icon` (falsy, `true`, or an object).
 * @param {Object<string, string>} [favicons] Host -> stored favicon URL, for `site.social`.
 * @returns {{kind: 'icon', icon: *, class: string}|{kind: 'battery', level: number, charging: boolean, lowPower: boolean}|{kind: 'favicon', src: string}|null}
 */
export function dynamicTagIcon(tagName, value, icon, favicons = {}) {
    if (!icon) {
        return null;
    }

    if (tagName === 'ambient.weather.condition') {
        return { kind: 'icon', icon: weatherFor(value).icon, class: DYNAMIC_TAG_ICON_CLASS };
    }

    if (tagName === 'ambient.battery.percent') {
        return {
            kind: 'battery',
            level: Number(value) / 100,
            charging: Boolean(icon.charging),
            lowPower: Boolean(icon.lowPower),
        };
    }

    // A count/date across every type has no single glyph to show.
    if (tagName.startsWith('entries.')) {
        return icon.type ? { kind: 'icon', icon: entryType(icon.type).icon, class: DYNAMIC_TAG_ICON_CLASS } : null;
    }

    // A typed streak borrows that type's glyph; an untyped one keeps the plain flame.
    if (tagName.startsWith('streak.')) {
        return { kind: 'icon', icon: icon.type ? entryType(icon.type).icon : 'FireIcon', class: DYNAMIC_TAG_ICON_CLASS };
    }

    if (tagName === 'site.social') {
        const host = hostOf(value);
        const favicon = host ? favicons[host] : null;

        return favicon
            ? { kind: 'favicon', src: favicon }
            : { kind: 'icon', icon: 'Globe02Icon', class: `${DYNAMIC_TAG_ICON_CLASS} text-neutral-400` };
    }

    return null;
}
