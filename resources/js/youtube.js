/** Extract an 11-character YouTube video id from any common URL shape. */
export function youtubeId(url) {
    if (!url) {
        return null;
    }

    const match = String(url).match(
        /(?:youtube\.com\/(?:watch\?v=|live\/|embed\/|shorts\/|v\/)|youtu\.be\/)([\w-]{11})/,
    );

    return match ? match[1] : null;
}
