/**
 * A label for a pasted URL, derived from what the path already says.
 *
 * Only ever consulted for a bare URL, where the author chose no words and the
 * fallback is the bare domain. A handler is right or absent: a general "the
 * last segment looks like a title" rule would read `/p/12345` and a date
 * segment as titles, and would silently mangle slugs that dropped an
 * apostrophe.
 */

/**
 * The repo whose name is dropped from a label, since almost every GitHub link
 * here points at it and repeating the name says nothing.
 */
const HOME_REPO = 'tdrayson/taylordrayson';

/** Enough of a commit hash to be recognisable, as GitHub itself abbreviates. */
const SHORT_HASH = 7;

/** Decode a path segment and give its separators back their spaces. */
function readable(segment) {
    try {
        return decodeURIComponent(segment).replace(/_/g, ' ');
    } catch {
        return segment.replace(/_/g, ' ');
    }
}

/**
 * `owner/repo` prefixed labels, minus the owner, and minus the repo when it is
 * the home one: `PR #191` rather than `tdrayson taylordrayson PR #191`.
 */
function scoped(owner, repo, suffix) {
    return `${owner}/${repo}` === HOME_REPO ? suffix : `${repo} ${suffix}`;
}

/**
 * @param {string[]} parts Path segments, already stripped of empties.
 * @returns {string|null}
 */
function github(parts) {
    const [owner, repo, kind, ...rest] = parts;

    if (owner === undefined) {
        return null;
    }

    // A profile, which is the one shape with no repo in it.
    if (repo === undefined) {
        return `@${owner}`;
    }

    if (kind === undefined) {
        return `${owner}/${repo}`;
    }

    if (kind === 'pull' && rest[0]) {
        return scoped(owner, repo, `PR #${rest[0]}`);
    }

    if (kind === 'issues' && rest[0]) {
        return scoped(owner, repo, `issue #${rest[0]}`);
    }

    if (kind === 'commit' && rest[0]) {
        return scoped(owner, repo, `@${rest[0].slice(0, SHORT_HASH)}`);
    }

    if (kind === 'releases' && rest[0] === 'tag' && rest[1]) {
        return scoped(owner, repo, rest[1]);
    }

    // A file, named by its basename: the full path is longer than the sentence
    // it sits in, and the leading `blob/{ref}` says nothing to a reader.
    if ((kind === 'blob' || kind === 'tree') && rest.length > 1) {
        return scoped(owner, repo, rest[rest.length - 1]);
    }

    return `${owner}/${repo}`;
}

/**
 * Each entry decides for its own host. Returning null falls through to the
 * domain, which is what an unrecognised shape should read as.
 *
 * @type {Array<{ hosts: (host: string) => boolean, label: (parts: string[]) => string|null }>}
 */
const HANDLERS = [
    {
        hosts: (host) => host === 'github.com',
        label: github,
    },
    {
        // Every language edition, and the mobile hosts alongside them.
        hosts: (host) => /(^|\.)wikipedia\.org$/.test(host),
        label: (parts) => (parts[0] === 'wiki' && parts[1] ? readable(parts[1]) : null),
    },
    {
        hosts: (host) => host === 'packagist.org',
        label: (parts) => (parts[0] === 'packages' && parts[2] ? `${parts[1]}/${parts[2]}` : null),
    },
    {
        hosts: (host) => host === 'npmjs.com',
        // A scoped package arrives as two segments, e.g. /package/@vue/reactivity.
        label: (parts) => (parts[0] === 'package' && parts[1] ? parts.slice(1).join('/') : null),
    },
];

/**
 * A label derived from the URL's own path, or null when nothing here knows the
 * host or recognises the shape.
 *
 * @param {string} href
 * @returns {string|null}
 */
export function urlLabel(href) {
    let url;

    try {
        url = new URL(href);
    } catch {
        return null;
    }

    const host = url.hostname.toLowerCase().replace(/^www\./, '');
    const parts = url.pathname.split('/').filter(Boolean);

    if (parts.length === 0) {
        return null;
    }

    for (const handler of HANDLERS) {
        if (handler.hosts(host)) {
            return handler.label(parts);
        }
    }

    return null;
}
