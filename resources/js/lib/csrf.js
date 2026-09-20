/**
 * The CSRF token for a fetch() call.
 *
 * Laravel ships it as the XSRF-TOKEN cookie rather than a meta tag here, and
 * expects it back URL-decoded in X-XSRF-TOKEN. Inertia's own visits carry it
 * already; anything posted with fetch has to add it or the request is a 419.
 *
 * @return {string} The token, or an empty string when the cookie is missing.
 */
export function csrf() {
    const cookie = document.cookie.split('; ').find((part) => part.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.slice('XSRF-TOKEN='.length)) : '';
}
