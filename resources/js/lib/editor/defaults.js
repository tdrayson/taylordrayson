/**
 * The starting value for a field with nothing in it yet.
 *
 * Lives here rather than in each page that builds a form, so /new, an entry
 * page and a content page cannot drift into disagreeing about what an empty
 * field is.
 */

const pad = (n) => String(n).padStart(2, '0');

/** Now as wall-clock text, the same shape the server stores. */
export function nowStamp() {
    const d = new Date();

    return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:00`;
}

export function defaultValueFor(field) {
    // Left empty so the server stamps it at save. Filling it on load dates the
    // entry when the form opened, which is wrong by however long you took.
    if (field.defaultsToNow) {
        return null;
    }

    // Where you are is a better guess than where you live, and it is the whole
    // reason the zone is stored per entry rather than once in config.
    if (field.source === 'timezone') {
        return Intl.DateTimeFormat().resolvedOptions().timeZone ?? '';
    }

    switch (field.type) {
        case 'rich-text':
        case 'prose':
        case 'tags':
        case 'image':
        case 'gallery':
            return [];
        case 'boolean':
            return false;
        // The first option is the type's default, so an article starts as a draft and a note as published.
        case 'status':
            return field.options?.[0]?.value ?? 'published';
        // Unset, not the first option: a required choice must be made, not
        // silently made for you. The input renders a placeholder row for this.
        case 'select':
            return '';
        default:
            return '';
    }
}

/**
 * Values for a whole form, taking what the record already has and falling back
 * to the empty value for its type. Dotted names address into nested data.
 */
export function valuesFor(fields, record = {}) {
    return Object.fromEntries(fields.map((field) => {
        const existing = field.name.split('.').reduce((carry, key) => carry?.[key], record);

        return [field.name, existing ?? defaultValueFor(field)];
    }));
}

/**
 * The slug a title would produce, matching Str::slug on the server.
 *
 * Punctuation is dropped rather than turned into a separator, which is what
 * Str::slug does: "Taylor's day" is taylors-day, not taylor-s-day.
 */
export function slugify(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]+/g, '')
        .trim()
        .replace(/[\s-]+/g, '-')
        .replace(/^-+|-+$/g, '');
}

/**
 * The same, while it is still being typed: a trailing separator survives, so
 * typing a space does not undo itself before the next word arrives.
 */
export function slugifyInput(value) {
    return String(value ?? '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .replace(/[^a-z0-9\s-]+/g, '')
        .replace(/[\s-]+/g, '-')
        .replace(/^-+/, '');
}

/**
 * A tag's name as it will be stored. Mirrors HasTags::titleCaseTag on the
 * server, so the chip shows what saving produces: an all-lowercase word is
 * capitalised, and a word already carrying a capital ("TV", "iOS") is left
 * exactly as it was typed.
 */
export function tagName(value) {
    return String(value ?? '').replace(
        /[\p{L}\p{N}']+/gu,
        (word) => (word === word.toLowerCase() ? word[0].toUpperCase() + word.slice(1) : word),
    );
}

/** How much of a note the derived slug uses. Mirrors Note::SLUG_WORDS. */
const NOTE_SLUG_WORDS = 6;

/**
 * The slug a note would fall back to, from its opening words.
 *
 * Mirrors Note::slugFrom() so the editor can preview the URL a note will get
 * before it is saved. The two have to agree.
 */
export function noteSlug(document, fallback = 'note') {
    const words = plainTextOf(document).trim().split(/\s+/).filter(Boolean);

    return slugWords(words.join(' ')) || fallback;
}

/** A name cut to the words a note slug uses, slugged. */
function slugWords(text) {
    return slugify(String(text ?? '').trim().split(/\s+/).slice(0, NOTE_SLUG_WORDS).join(' '));
}

/** A URL's host without www., slugged, or '' when it does not parse yet. */
function domainSlug(url) {
    try {
        return slugify(new URL(url).hostname.toLowerCase().replace(/^www\./, '').replaceAll('.', ' '));
    } catch {
        return '';
    }
}

/**
 * The slug a response note is stored with when none is written, e.g.
 * `reply-to-sending-your-first-webmention`. Mirrors NameResponseSlug.
 *
 * @param {{kind: string, url: string, rsvp?: string, preview?: object|null}} response The
 *        response fields, plus the context CitationField fetched for the URL, if any.
 * @returns {string|null} Null for a plain note, or while nothing names the target.
 */
export function responseSlug({ kind, url, rsvp = null, preview = null }) {
    // An RSVP with no answer is not one, the same as PostType::of() on the server.
    if (! kind || ! url || (kind === 'rsvp' && ! rsvp)) {
        return null;
    }

    // A preview still describing the previous URL would name the wrong post.
    const context = preview?.url === url ? preview : null;
    const cited = context?.cited ?? null;

    const candidates = {
        reply: [cited?.title, cited?.authorName],
        rsvp: [cited?.title],
    }[kind] ?? [];

    const name = context?.internal
        ? slugWords(context.title)
        : candidates.map(slugWords).find(Boolean) ?? domainSlug(url);

    if (! name) {
        return null;
    }

    return `${kind === 'reply' ? 'reply-to' : kind}-${name}`;
}

/**
 * The readable text of a Portable Text document, ignoring its structure.
 *
 * Mirrors PortableText::plainText(), which the note length limit is measured
 * with: spans join with nothing and blocks with a space, so marking a word as
 * a link cannot change the count. The two have to agree or the counter will
 * disagree with the save.
 */
export function plainTextOf(document) {
    if (typeof document === 'string') {
        return document;
    }

    const parts = (Array.isArray(document) ? document : []).map((node) => (node?._type === 'code'
        ? node.code ?? ''
        : (node?.children ?? []).map((child) => child?.text ?? '').join('')));

    return parts.join(' ').replace(/\s+/g, ' ').trim();
}
