import { reactive } from 'vue';

/**
 * Global media-player state. Lives outside the page tree (consumed by the
 * persistent MediaPlayer in AppLayout) so playback survives Inertia visits.
 *
 * track shape: { id, title, audioUrl, youtubeUrl, thumbnail }
 * dockEl: the inline slot element the video should overlay; null = corner mini.
 */
export const player = reactive({
    track: null,
    mode: null, // 'audio' | 'video'
    playing: false,
    dockEl: null,
});

export function playAudio(track) {
    player.track = track;
    player.mode = 'audio';
    player.playing = true;
    player.dockEl = null;
}

export function playVideo(track) {
    player.track = track;
    player.mode = 'video';
    player.playing = true;
    player.dockEl = null;
}

export function togglePlay() {
    player.playing = !player.playing;
}

export function closePlayer() {
    player.track = null;
    player.mode = null;
    player.playing = false;
    player.dockEl = null;
}

/** Pin the playing video to an inline slot element (the entry page area). */
export function dockVideo(element) {
    player.dockEl = element;
}

/** Release the inline slot (e.g. on page leave) so the video docks to the corner. */
export function undockVideo(element) {
    if (player.dockEl === element) {
        player.dockEl = null;
    }
}

/** Is this track the one currently loaded in the given mode? */
export function isCurrent(track, mode) {
    return player.mode === mode && player.track?.id === track?.id;
}
