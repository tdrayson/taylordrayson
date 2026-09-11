import { reactive, ref } from 'vue';
import { optionsEqual } from '../lib/editor/optionsEqual';

/**
 * The dynamic tag registry, fetched once per page load and shared by every
 * chip and the "{" menu on it. Module-level rather than per-call: the
 * composable is instantiated by each chip's node view as well as the editor,
 * and without this a page with several chips would fire one request per chip.
 */
const tags = ref([]);
let request = null;

/**
 * Preview text resolved for a non-default option set, keyed by tag name and a
 * key-sorted encoding of its options so `{type: 'a', period: 'b'}` and
 * `{period: 'b', type: 'a'}` share one entry. Reactive so a chip or the
 * options popup, both of which read it through `previewFor`, update the moment
 * a lazily-fired request settles rather than needing to re-poll.
 */
const resolved = reactive({});

/**
 * A stable cache key regardless of the order the caller built its options in.
 * Placement is part of the key because an `href` preview and an `inline` one
 * can be different strings for the same tag and options.
 */
function resolvedKey(name, options, placement) {
    const parts = Object.keys(options).sort().map((key) => `${key}=${options[key]}`);

    return `${placement}:${name}?${parts.join('&')}`;
}

/**
 * Resolves a tag against an arbitrary option set via the preview endpoint:
 * its display text, typed value and icon payload (mirroring what a resolved
 * span carries in production). Failure degrades to null, the same "nothing to
 * show" state an unresolvable tag already has.
 *
 * @param {string} name
 * @param {Object<string, string>} options
 * @param {'inline'|'href'|'image'} placement
 * @returns {Promise<{preview: string|null, value: *, icon: *}|null>}
 */
async function fetchPreview(name, options, placement) {
    const query = new URLSearchParams({ name, placement });

    for (const [key, value] of Object.entries(options)) {
        query.append(`options[${key}]`, value);
    }

    try {
        const response = await fetch(`/dynamic-tags/preview?${query.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        return response.ok ? ((await response.json()).data ?? null) : null;
    } catch {
        return null;
    }
}

/**
 * Fetches the registry the first time it is needed; later calls reuse the same
 * pending or settled request. A failed fetch degrades to an empty list rather
 * than throwing, matching how the mention menu handles a failed lookup.
 */
function ensureLoaded() {
    if (! request) {
        request = fetch('/dynamic-tags', {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        })
            .then((response) => (response.ok ? response.json() : { data: [] }))
            .then((body) => { tags.value = body.data ?? []; })
            .catch(() => { tags.value = []; });
    }

    return request;
}

/**
 * The options a freshly picked tag starts with, mirroring the server's own
 * default resolution (`DynamicTagsPayload::defaults()`) so a chip inserted
 * before the options popup exists matches the preview it was picked from.
 *
 * @param {{options: Array<{name: string, default: string|null}>}} tag
 * @returns {Object<string, string>}
 */
export function defaultOptionsFor(tag) {
    const options = {};

    for (const option of tag.options ?? []) {
        if (option.default !== null) {
            options[option.name] = option.default;
        }
    }

    return options;
}

/**
 * Fetches (once per key) and returns the cached preview entry for a non-default
 * option set, or the still-loading `null` placeholder until it settles.
 *
 * @returns {{preview: string|null, value: *, icon: *}|null}
 */
function resolvedEntry(name, options, placement) {
    const key = resolvedKey(name, options, placement);

    if (! (key in resolved)) {
        resolved[key] = null;
        fetchPreview(name, options, placement).then((entry) => { resolved[key] = entry; });
    }

    return resolved[key];
}

/**
 * The fetched tag list, plus the value each one currently reads as.
 *
 * @returns {{tags: import('vue').Ref<Array>, previewFor: (name: string, options?: Object, placement?: 'inline'|'href'|'image') => string|null, iconFor: (name: string, options?: Object) => *, valueFor: (name: string, options?: Object) => *, ensureLoaded: () => Promise<void>}}
 */
export function useDynamicTags() {
    ensureLoaded();

    /**
     * The live text a chip or the options popup shows in place of the raw tag
     * name, or the URL it resolves to when it stands in for a link's href or
     * an image's src. Options are part of the lookup, not just the name,
     * because `entries.count` with `type: calorie` and without it are
     * different values, and placement matters because a tag's href and its
     * display text can differ.
     *
     * The inline default option set resolves instantly from the list already
     * loaded by `ensureLoaded`, which is always the display text. Anything
     * else is resolved lazily against the preview endpoint and cached in
     * `resolved`; the caller sees null until that settles, then gets the real
     * value on the next reactive read. Callers that mutate options on every
     * keystroke (the popup) must debounce what they pass in themselves, since
     * every distinct call here that isn't already cached fires its own
     * request.
     */
    function previewFor(name, options = {}, placement = 'inline') {
        const tag = tags.value.find((candidate) => candidate.name === name);

        if (! tag) {
            return null;
        }

        if (placement === 'inline' && optionsEqual(options, defaultOptionsFor(tag))) {
            return tag.preview;
        }

        return resolvedEntry(name, options, placement)?.preview ?? null;
    }

    /**
     * The icon payload for a chip's current options, mirroring what a resolved
     * span carries. The icon option defaults to off, so the default option set
     * never needs a request: it can never carry one. Inline only, since a
     * tag's icon has no meaning as a link href or image source.
     */
    function iconFor(name, options = {}) {
        const tag = tags.value.find((candidate) => candidate.name === name);

        if (! tag || optionsEqual(options, defaultOptionsFor(tag))) {
            return null;
        }

        return resolvedEntry(name, options, 'inline')?.icon ?? null;
    }

    /** The typed value behind a chip's current options, for icon rendering. */
    function valueFor(name, options = {}) {
        const tag = tags.value.find((candidate) => candidate.name === name);

        if (! tag || optionsEqual(options, defaultOptionsFor(tag))) {
            return null;
        }

        return resolvedEntry(name, options, 'inline')?.value ?? null;
    }

    return { tags, previewFor, iconFor, valueFor, ensureLoaded };
}
