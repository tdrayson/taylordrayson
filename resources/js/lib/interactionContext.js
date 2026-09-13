import { computed, inject, provide } from 'vue';

const INTERACTION_CONTEXT = Symbol('interactionContext');
const INTERACTION_PENDING = Symbol('interactionPending');

const EMPTY = computed(() => ({}));
const SETTLED = computed(() => false);

/**
 * Share a page's reaction and response counts with the cards beneath it.
 *
 * Provided rather than passed down, for the reason linkContext is: only the
 * card reads it, and it sits two components below the page that loads it.
 * Deferred, so this is empty on first paint and fills on the second request.
 *
 * @param {import('vue').Ref<Object>} source Keyed `type:id`.
 * @param {import('vue').Ref<boolean>} [pending] True until the deferred request lands.
 */
export function provideInteractions(source, pending = SETTLED) {
    provide(INTERACTION_CONTEXT, source);
    provide(INTERACTION_PENDING, pending);
}

/** @return {import('vue').Ref<Object>} */
export function useInteractions() {
    return inject(INTERACTION_CONTEXT, EMPTY);
}

/**
 * Whether the counts are still on their way, so a card can hold the row's space
 * rather than growing when they arrive. False on a page that never loads them.
 *
 * @return {import('vue').Ref<boolean>}
 */
export function useInteractionsPending() {
    return inject(INTERACTION_PENDING, SETTLED);
}
