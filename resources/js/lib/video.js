import { youtubeId } from './youtube.js';

const VIMEO = /vimeo\.com\/(?:video\/)?(\d+)/;
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

    const file = String(url).match(FILE);
    if (file) {
        return { provider: 'html5', src: url, mime: MIME[file[1].toLowerCase()] ?? 'video/mp4' };
    }

    return null;
}
