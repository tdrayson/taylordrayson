import { computed, inject, provide } from 'vue';

const INTERACTION_CONTEXT = Symbol('interactionContext');

const EMPTY = computed(() => ({}));

/**
 * Share a page's reaction and response counts with the cards beneath it.
 *
 * Provided rather than passed down, for the reason linkContext is: only the
 * card reads it, and it sits two components below the page that loads it.
 * Deferred, so this is empty on first paint and fills on the second request.
 *
 * @param {import('vue').Ref<Object>} source Keyed `type:id`.
 */
export function provideInteractions(source) {
    provide(INTERACTION_CONTEXT, source);
}

/** @return {import('vue').Ref<Object>} */
export function useInteractions() {
    return inject(INTERACTION_CONTEXT, EMPTY);
}
