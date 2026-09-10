import { youtubeId } from './youtube.js';

const VIMEO = /vimeo\.com\/(?:video\/)?(\d+)/;
const LOOM = /loom\.com\/(?:share|embed)\/([0-9a-f]{32})/i;
const FILE = /\.(mp4|m4v|mov|webm|ogv|ogg)(\?.*)?$/i;

const MIME = {
    mp4: 'video/mp4',
    m4v: 'video/mp4',
    mov: 'video/mp4',
    webm: 'video/webm',
    ogv: 'video/ogg',
    ogg: 'video/ogg',
};

/**
 * Classify a video URL into a Plyr-ready source: a YouTube or Vimeo embed id, or
 * a direct file with its MIME type. Returns null when the URL is unrecognised.
 */
export function videoSource(url) {
    if (!url) {
        return null;
    }

    const youtube = youtubeId(url);
    if (youtube) {
        return { provider: 'youtube', id: youtube };
    }

    const vimeo = String(url).match(VIMEO);
    if (vimeo) {
        return { provider: 'vimeo', id: vimeo[1] };
    }

    const loom = String(url).match(LOOM);
    if (loom) {
        return { provider: 'loom', id: loom[1] };
    }

    const file = String(url).match(FILE);
    if (file) {
        return { provider: 'html5', src: url, mime: MIME[file[1].toLowerCase()] ?? 'video/mp4' };
    }

    return null;
}

// Player URLs for the hosts that need an iframe. YouTube goes through nocookie
// for the same reason the docked player does: no tracking cookie for a video
// nobody has pressed play on yet.
const EMBEDS = {
    youtube: (id, autoplay) => `https://www.youtube-nocookie.com/embed/${id}?rel=0${autoplay ? '&autoplay=1' : ''}`,
    vimeo: (id, autoplay) => `https://player.vimeo.com/video/${id}${autoplay ? '?autoplay=1' : ''}`,
    loom: (id, autoplay) => `https://www.loom.com/embed/${id}${autoplay ? '?autoplay=1' : ''}`,
};

/** Display name per provider, for a placeholder that says what it will load. */
const PROVIDERS = { youtube: 'YouTube', vimeo: 'Vimeo', loom: 'Loom' };

/**
 * The iframe URL for a video held by a provider. Null for a direct file, which
 * belongs in a <video> element, and for anything unrecognised. `autoplay` is for
 * the case where the reader has already pressed play on a placeholder.
 */
export function videoEmbed(url, { autoplay = false } = {}) {
    const source = videoSource(url);

    return source && EMBEDS[source.provider] ? EMBEDS[source.provider](source.id, autoplay) : null;
}

/**
 * Posters for a video, best first, without loading its player. YouTube publishes
 * these on a plain image host; Vimeo needs an API call for one, so it gets none
 * and falls back to the plain placeholder.
 *
 * maxresdefault is the only 16:9 size and the only one sharp enough for a full
 * content column, but it exists only for videos uploaded in HD. hqdefault always
 * resolves, at the cost of being 4:3 with the video letterboxed inside it, so it
 * is the fallback rather than the first choice.
 *
 * @returns {string[]} Candidate URLs, or an empty list for a host with none.
 */
export function videoThumbnails(url) {
    const source = videoSource(url);

    if (source?.provider !== 'youtube') {
        return [];
    }

    return ['maxresdefault', 'hqdefault'].map((size) => `https://i.ytimg.com/vi/${source.id}/${size}.jpg`);
}

/** The host a URL will stream from, or null when it is a direct file. */
export function videoProvider(url) {
    const source = videoSource(url);

    return source ? (PROVIDERS[source.provider] ?? null) : null;
}
