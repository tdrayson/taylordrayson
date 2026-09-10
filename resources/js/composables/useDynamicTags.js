import { ref } from 'vue';

/**
 * The dynamic tag registry, fetched once per page load and shared by every
 * chip and the "{" menu on it. Module-level rather than per-call: the
 * composable is instantiated by each chip's node view as well as the editor,
 * and without this a page with several chips would fire one request per chip.
 */
const tags = ref([]);
let request = null;

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
     * The live text a chip shows in place of its raw tag name. Options are
     * part of the lookup, not just the name, because `entries.count` with
     * `type: calorie` and without it are different values. There is no
     * per-option resolver yet, so anything other than the tag's own declared
     * defaults has no known value to show and falls back to the caller
     * showing the tag name instead.
     */
    function previewFor(name, options = {}) {
        const tag = tags.value.find((candidate) => candidate.name === name);

        if (! tag) {
            return null;
        }

        const atDefaults = JSON.stringify(options) === JSON.stringify(defaultOptionsFor(tag));

        return atDefaults ? tag.preview : null;
    }

    return { tags, previewFor, ensureLoaded };
}
