import { computed, ref } from 'vue';

// True from the moment a filter is picked until its view transition ends, so
// the timeline names its cards for that swap only and never for an entry morph.
const active = ref(false);

// Set once the browser starts the transition. A visit that never gets one (no
// support, a hidden tab, a failed request) resets on finish instead.
let running = false;

function reset() {
    active.value = false;
    running = false;
}

/**
 * Visit options for a timeline filter link, naming the cards for the swap.
 *
 * @return {Object} Options for router.visit, or props for <Link>.
 */
export function filterVisit() {
    return {
        onBefore: () => {
            active.value = true;
        },
        viewTransition: (transition) => {
            running = true;
            transition.finished.finally(reset);
        },
        onFinish: () => {
            if (! running) {
                reset();
            }
        },
    };
}

/**
 * Whether a filter swap is under way.
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useFilterTransition() {
    return computed(() => active.value);
}
