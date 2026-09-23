/**
 * The Open Graph payload a view publishes, and the share card it resolves to.
 *
 * Both the head tags and the "Sharing this?" panel read from here, so what a
 * reader is shown as the preview is the same URL a scraper is handed, rather
 * than two builders that agree until one of them is edited.
 */

/**
 * A view's metadata with defaults applied, so a partial `og` still renders.
 *
 * @param {object} og The view's Open Graph payload.
 * @param {?string} description The site bio, for a view with no description of its own.
 * @returns {object}
 */
export function ogMeta(og, description = null) {
    return {
        title: null,
        description,
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
 * The absolute URL of a view's share card, which OgMeta signs server-side.
 *
 * @param {object} og The view's Open Graph payload.
 * @param {string} origin Absolute base URL, for a relative image.
 * @returns {?string} An absolute URL, or null when the view has no card.
 */
export function ogCardUrl(og, origin) {
    const image = og?.image;

    if (!image) {
        return null;
    }

    return image.startsWith('http') ? image : `${origin}${image}`;
}
