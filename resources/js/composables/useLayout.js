import { reactive } from 'vue';
import { router } from '@inertiajs/vue3';

/**
 * Reactive layout props shared between a page and AppLayout.
 * Pages write to this store via setLayoutProps(); AppLayout reads from it.
 * This is a v2-compatible replacement for Inertia v3's built-in setLayoutProps.
 *
 * @type {{ breadcrumb: Array<{label: string, href?: string}> }}
 */
export const layoutProps = reactive({ breadcrumb: [] });

/**
 * Merge per-page props into the shared layout store.
 * Drop-in replacement for Inertia v3's removed setLayoutProps().
 *
 * @param {object} props - Props to merge (e.g. { breadcrumb: [...] })
 */
export function setLayoutProps(props) {
    Object.assign(layoutProps, props);
}

// Reset breadcrumb between page visits so props do not leak across navigations.
router.on('start', () => {
    layoutProps.breadcrumb = [];
});
