<?php

namespace App\Support;

class YouTube
{
    /**
     * Extract the 11-character YouTube video id from any common URL shape
     * (watch?v=, youtu.be/, live/, embed/, shorts/, v/).
     */
    public static function id(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $pattern = '#(?:youtube\.com/(?:watch\?v=|live/|embed/|shorts/|v/)|youtu\.be/)([\w-]{11})#';

        return preg_match($pattern, $url, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Build the max-resolution thumbnail URL for a YouTube video URL, or null
     * when the URL does not contain a recognisable video id.
     */
    public static function thumbnail(?string $url): ?string
    {
        $id = self::id($url);

        return $id ? "https://i.ytimg.com/vi/{$id}/maxresdefault.jpg" : null;
    }
}
