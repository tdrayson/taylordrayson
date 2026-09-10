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

/** A stable cache key regardless of the order the caller built its options in. */
function resolvedKey(name, options) {
    const parts = Object.keys(options).sort().map((key) => `${key}=${options[key]}`);

    return `${name}?${parts.join('&')}`;
}

/**
 * Resolves a tag against an arbitrary option set via the preview endpoint.
 * Failure degrades to null, the same "nothing to show" state an unresolvable
 * tag already has.
 *
 * @param {string} name
 * @param {Object<string, string>} options
 * @returns {Promise<string|null>}
 */
async function fetchPreview(name, options) {
    const query = new URLSearchParams({ name });

    for (const [key, value] of Object.entries(options)) {
        query.append(`options[${key}]`, value);
    }

    try {
        const response = await fetch(`/dynamic-tags/preview?${query.toString()}`, {
            headers: { Accept: 'application/json' },
            credentials: 'same-origin',
        });

        return response.ok ? ((await response.json()).data?.preview ?? null) : null;
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
 * The fetched tag list, plus the value each one currently reads as.
 *
 * @returns {{tags: import('vue').Ref<Array>, previewFor: (name: string, options?: Object) => string|null, ensureLoaded: () => Promise<void>}}
 */
export function useDynamicTags() {
    ensureLoaded();

    /**
     * The live text a chip or the options popup shows in place of the raw tag
     * name. Options are part of the lookup, not just the name, because
     * `entries.count` with `type: calorie` and without it are different
     * values.
     *
     * The default option set resolves instantly from the list already loaded
     * by `ensureLoaded`. Anything else is resolved lazily against the preview
     * endpoint and cached in `resolved`; the caller sees null until that
     * settles, then gets the real value on the next reactive read. Callers
     * that mutate options on every keystroke (the popup) must debounce what
     * they pass in themselves, since every distinct call here that isn't
     * already cached fires its own request.
     */
    function previewFor(name, options = {}) {
        const tag = tags.value.find((candidate) => candidate.name === name);

        if (! tag) {
            return null;
        }

        if (optionsEqual(options, defaultOptionsFor(tag))) {
            return tag.preview;
        }

        const key = resolvedKey(name, options);

        if (! (key in resolved)) {
            resolved[key] = null;
            fetchPreview(name, options).then((text) => { resolved[key] = text; });
        }

        return resolved[key];
    }

    return { tags, previewFor, ensureLoaded };
}
