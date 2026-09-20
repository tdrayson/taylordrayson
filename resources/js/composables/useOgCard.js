import { computed, toValue } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { ogCardUrl } from '../lib/og.js';

/**
 * The share card URL for an Open Graph payload, resolved against the shared
 * site identity.
 *
 * @param {object|Function} og The view's `og` prop, or a getter for it.
 * @returns {import('vue').ComputedRef<string>} An absolute URL.
 */
export function useOgCard(og) {
    const page = usePage();

    // The shared appUrl rather than the browser's, so og:url and og:image
    // resolve during SSR where window is undefined.
    const origin = computed(() => page.props.appUrl
        ?? (typeof window === 'undefined' ? '' : window.location.origin));

    return computed(() => ogCardUrl(toValue(og), {
        origin: origin.value,
        siteName: page.props.identity.name,
        version: page.props.ogVersion,
    }));
}
