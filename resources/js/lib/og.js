/**
 * The Open Graph payload a view publishes, and the share card it resolves to.
 *
 * Both the head tags and the "Sharing this?" panel read from here, so what a
 * reader is shown as the preview is the same URL a scraper is handed, rather
 * than two builders that agree until one of them is edited.
 */

// SEO copy for pages with no page-specific description, not the profile bio:
// intentionally its own wording, not identity.bio.
export const DEFAULT_DESCRIPTION =
    'I build things on the internet, track everything, and drink too much coffee. A living archive of what I make, watch, read, and get up to.';

/** A view's metadata with defaults applied, so a partial `og` still renders. */
export function ogMeta(og) {
    return {
        title: null,
        description: DEFAULT_DESCRIPTION,
        heading: null,
        eyebrow: null,
        accent: null,
        image: null,
        variant: null,
        type: 'website',
        noindex: false,
        ...og,
    };
}

/**
 * The absolute URL of a view's share card.
 *
 * An explicit image wins, which is how an entry gets its pre-rendered card.
 * Everything else composes the generated card from the heading (falling back to
 * the title), eyebrow, accent and variant.
 *
 * @param {object} og The view's Open Graph payload.
 * @param {{origin: string, siteName: string, version: ?string}} context Absolute base URL, site name for an untitled view, and the card design token.
 * @returns {string} An absolute URL.
 */
export function ogCardUrl(og, { origin, siteName, version }) {
    const meta = ogMeta(og);

    if (meta.image) {
        return meta.image.startsWith('http') ? meta.image : `${origin}${meta.image}`;
    }

    const params = new URLSearchParams({ title: meta.heading ?? meta.title ?? siteName });

    if (meta.eyebrow) {
        params.set('eyebrow', meta.eyebrow);
    }

    if (meta.accent) {
        params.set('accent', meta.accent);
    }

    if (meta.variant) {
        params.set('variant', meta.variant);

        // The home card has room for a standfirst, and the page's own
        // description is what belongs there: a second hardcoded line on the
        // renderer could drift from the one the page publishes.
        params.set('description', meta.description);
    }

    // Cards are served immutable, so the URL moving is the only thing that
    // makes a scraper fetch a new design.
    if (version) {
        params.set('v', version);
    }

    return `${origin}/og.png?${params.toString()}`;
}
