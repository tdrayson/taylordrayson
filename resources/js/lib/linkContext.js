import { computed, inject, provide } from 'vue';

const LINK_CONTEXT = Symbol('linkContext');

const EMPTY = computed(() => ({ previews: {}, favicons: {} }));

/**
 * Share a page's resolved link data with the prose beneath it.
 *
 * Only BlockContent reads it, but it sits three components down, so passing it
 * by prop meant every layer in between declaring two props it never used.
 *
 * @param {import('vue').Ref<{previews: Object, favicons: Object}>} source
 */
export function provideLinkContext(source) {
    provide(LINK_CONTEXT, source);
}

/** @return {import('vue').Ref<{previews: Object, favicons: Object}>} */
export function useLinkContext() {
    return inject(LINK_CONTEXT, EMPTY);
}
