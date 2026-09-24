import { router } from '@inertiajs/vue3';

/**
 * The stored id of a Strava or Swarm response that can be marked as mine.
 *
 * Syndicated responses only: a comment left here already knows whether it was
 * mine from the address it was written with.
 *
 * @param {{ id: string }} item a response row, conversation or hub
 * @returns {string|null} the stored id, or null when it cannot be marked
 */
export function markableId(item) {
    const match = /^syndicated-(\d+)$/.exec(item.id ?? '');

    return match ? match[1] : null;
}

/**
 * Marks or unmarks a reply as mine, then lets Inertia reload the page's props.
 *
 * @param {string} id the stored response id, from markableId()
 * @param {boolean} mine the state to set
 * @returns {void}
 */
export function setMine(id, mine) {
    router.patch(`/responses/syndicated/${id}/mine`, { mine }, { preserveScroll: true });
}
