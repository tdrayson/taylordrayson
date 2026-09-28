import { computed, ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

// The path of the entry page a morph is landing on, so that page names the
// parts it receives. Null whenever no morph is running.
const target = ref(null);

// Elements the leaving page named for the current morph, unnamed afterwards.
let named = [];

// Set once the browser actually starts the transition. A visit that never gets
// one (no support, a hidden tab, a failed request) resets on finish instead.
let running = false;

/**
 * An href as a bare path, the form the entry page compares against.
 *
 * @param {string} href Relative, or absolute to this site.
 * @return {string}
 */
function pathOf(href) {
    return new URL(href, window.location.origin).pathname;
}

/**
 * Give one element a morph name. A named element that is hidden or broken
 * across lines aborts the whole transition, so only a single box qualifies.
 *
 * @param {Element} el The element to name.
 * @param {string} part Which part of the entry it is: icon, label, title, excerpt, body or image.
 */
function name(el, part) {
    if (el.getClientRects().length !== 1) {
        return;
    }

    el.style.viewTransitionName = `morph-${part}`;
    named.push(el);
}

function unname() {
    named.forEach((el) => el.style.removeProperty('view-transition-name'));
    named = [];
}

function reset() {
    unname();
    running = false;
    target.value = null;
}

/**
 * Visit options that morph the parts `prime` names into the entry page at `href`.
 *
 * @param {string} href The entry page being visited.
 * @param {() => void} prime Names the leaving page's parts, called just before the visit.
 * @return {Object} Options for router.visit, or props for <Link>.
 */
function morphOptions(href, prime) {
    return {
        onBefore: () => {
            unname();
            prime();
            target.value = pathOf(href);
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
 * Link props that morph a timeline card into its entry page. Every
 * `[data-morph]` element in the card is named after its part; the first of
 * each wins, so a card can't hand the page two titles.
 *
 * @param {import('vue').Ref<Element|null>} card The card's root element.
 * @param {string} href The entry page the card links to.
 * @return {Object}
 */
export function cardMorph(card, href) {
    return morphOptions(href, () => {
        const seen = new Set();

        card.value?.querySelectorAll('[data-morph]').forEach((el) => {
            if (! seen.has(el.dataset.morph)) {
                seen.add(el.dataset.morph);
                name(el, el.dataset.morph);
            }
        });
    });
}

/**
 * Click handler for prose: a link chip that resolves to an entry morphs into
 * that entry's page. The anchor keeps its real href, so a parser or a reader
 * without JS still follows the plain link.
 *
 * @param {MouseEvent} event The click, delegated from the prose element.
 * @param {Object} previews href -> preview, the chips that resolve to an entry.
 */
export function morphFromProse(event, previews) {
    const anchor = event.target.closest?.('a[href]');
    const href = anchor?.getAttribute('href');

    if (! href || ! previews[href] || event.defaultPrevented || event.button !== 0
        || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey || anchor.target) {
        return;
    }

    event.preventDefault();

    router.visit(href, morphOptions(href, () => {
        const icon = anchor.querySelector('svg');

        if (icon) {
            name(icon, 'icon');
        }

        name(anchor, 'title');
    }));
}

/**
 * Whether the current page is the one a morph is landing on.
 *
 * @return {import('vue').ComputedRef<boolean>}
 */
export function useMorphTarget() {
    const page = usePage();

    return computed(() => target.value !== null && target.value === pathOf(page.url));
}
