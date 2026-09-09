/**
 * How a downloadable file describes itself: its icon, what to call its type,
 * and its size in words. Shared by the published card and the editor block so
 * a file looks the same while it is being written as it does once published.
 */

const UNITS = ['B', 'KB', 'MB', 'GB'];

// Extension first, mime second. finfo rarely returns application/json for a
// small JSON file and browsers disagree about zip, but the name the author
// uploaded is unambiguous.
const KINDS = {
    zip: { icon: 'Zip01Icon', label: 'ZIP archive' },
    gz: { icon: 'Zip01Icon', label: 'Gzip archive' },
    tar: { icon: 'Zip01Icon', label: 'TAR archive' },
    pdf: { icon: 'Pdf01Icon', label: 'PDF' },
    txt: { icon: 'Txt01Icon', label: 'Text file' },
    md: { icon: 'Txt01Icon', label: 'Markdown' },
    csv: { icon: 'Csv01Icon', label: 'CSV' },
    json: { icon: 'SourceCodeIcon', label: 'JSON' },
    xml: { icon: 'Xml01Icon', label: 'XML' },
    doc: { icon: 'Doc01Icon', label: 'Word document' },
    docx: { icon: 'Doc01Icon', label: 'Word document' },
    rtf: { icon: 'Doc01Icon', label: 'Rich text' },
    xls: { icon: 'Xls01Icon', label: 'Excel spreadsheet' },
    xlsx: { icon: 'Xls01Icon', label: 'Excel spreadsheet' },
    ppt: { icon: 'Ppt01Icon', label: 'PowerPoint' },
    pptx: { icon: 'Ppt01Icon', label: 'PowerPoint' },
};

const MIMES = {
    'application/zip': KINDS.zip,
    'application/x-zip-compressed': KINDS.zip,
    'application/gzip': KINDS.gz,
    'application/x-gzip': KINDS.gz,
    'application/x-tar': KINDS.tar,
    'application/pdf': KINDS.pdf,
    'text/plain': KINDS.txt,
    'text/markdown': KINDS.md,
    'text/csv': KINDS.csv,
    'application/json': KINDS.json,
    'application/xml': KINDS.xml,
    'text/xml': KINDS.xml,
    'application/msword': KINDS.doc,
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document': KINDS.docx,
    'application/rtf': KINDS.rtf,
    'application/vnd.ms-excel': KINDS.xls,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': KINDS.xlsx,
    'application/vnd.ms-powerpoint': KINDS.ppt,
    'application/vnd.openxmlformats-officedocument.presentationml.presentation': KINDS.pptx,
};

const UNKNOWN = { icon: 'File01Icon', label: 'File' };

/**
 * The icon and type label for a file.
 *
 * @param {string|null} name The filename, whose extension is trusted first.
 * @param {string|null} mime The stored mime type, used when the name has none.
 * @returns {{icon: string, label: string}}
 */
export function fileKind(name, mime) {
    const extension = String(name ?? '').split('.').pop()?.toLowerCase();

    return KINDS[extension] ?? MIMES[mime] ?? UNKNOWN;
}

/**
 * A byte count in words, or null when there is no count to show.
 *
 * @param {number|null} bytes
 * @returns {string|null}
 */
export function formatBytes(bytes) {
    if (typeof bytes !== 'number' || ! Number.isFinite(bytes) || bytes < 0) {
        return null;
    }

    let value = bytes;
    let unit = 0;

    while (value >= 1024 && unit < UNITS.length - 1) {
        value /= 1024;
        unit += 1;
    }

    // Whole numbers below a megabyte: "1.5 KB" is false precision for a figure
    // nobody acts on, while megabytes upward want a decimal to stay useful.
    return `${unit >= 2 ? value.toFixed(1) : Math.round(value)} ${UNITS[unit]}`;
}

/**
 * GitHub's own permanent redirect to the newest release asset. Resolving it is
 * GitHub's job, not ours, which is why a release node can never hold a dead
 * link however stale the metadata beside it has gone.
 *
 * @param {string|null} repo `owner/name`
 * @param {string|null} asset The asset filename, constant across releases.
 * @returns {string|null}
 */
export function githubDownloadUrl(repo, asset) {
    if (! repo || ! asset) {
        return null;
    }

    return `https://github.com/${repo}/releases/latest/download/${encodeURIComponent(asset)}`;
}

/**
 * The release the download comes from, for a reader who wants the notes and
 * the source rather than the file. Tracks `latest` for the same reason the
 * download does, so the two can never point at different releases.
 *
 * @param {string|null} repo `owner/name`
 * @returns {string|null}
 */
export function githubReleaseUrl(repo) {
    return repo ? `https://github.com/${repo}/releases/latest` : null;
}

/** A tag as `v1.0.4`, whether it was published with the prefix or without. */
export function versionLabel(version) {
    if (! version) {
        return null;
    }

    const trimmed = String(version).trim();

    return /^v/i.test(trimmed) ? `v${trimmed.slice(1)}` : `v${trimmed}`;
}

// Compound suffixes, so a release tarball reads foo-v1.0.4.tar.gz rather than
// foo.tar-v1.0.4.gz.
const DOUBLE_EXTENSIONS = ['.tar.gz', '.tar.bz2', '.tar.xz'];

/**
 * The filename with its version in it.
 *
 * A release asset has to keep one constant name for the latest-download URL to
 * resolve, so the version cannot live in the file GitHub holds. It goes into
 * the name shown here instead, which is the one place it can say which build
 * this is.
 *
 * @param {string|null} name
 * @param {string|null} version Already labelled, e.g. `v1.0.4`.
 * @returns {string|null}
 */
export function versionedFilename(name, version) {
    if (! name || ! version || name.includes(version)) {
        return name ?? null;
    }

    const suffix = DOUBLE_EXTENSIONS.find((extension) => name.toLowerCase().endsWith(extension));
    const cut = suffix ? name.length - suffix.length : name.lastIndexOf('.');

    return cut <= 0 ? `${name}-${version}` : `${name.slice(0, cut)}-${version}${name.slice(cut)}`;
}
