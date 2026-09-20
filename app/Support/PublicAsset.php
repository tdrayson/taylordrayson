<?php

namespace App\Support;

/**
 * Cache-busting URLs for the static files served straight out of public/.
 *
 * Those files are sent with a ten-year max-age and their names never change, so
 * nothing tells a browser the bytes moved. A hash of the contents does, and
 * only when they actually change: a deploy stamp would throw the cache away on
 * every push.
 */
final class PublicAsset
{
    /** @var array<string, string> */
    private static array $urls = [];

    /**
     * $path with a ?v= query carrying a short hash of the file's contents, or
     * unchanged when no such file exists.
     *
     * Memoised in a static rather than the cache store: this runs in the head of
     * every page, a request is the whole useful lifetime, and production's store
     * is volatile anyway.
     */
    public static function url(string $path): string
    {
        if (isset(self::$urls[$path])) {
            return self::$urls[$path];
        }

        $file = public_path(ltrim($path, '/'));
        $hash = is_file($file) ? md5_file($file) : false;

        return self::$urls[$path] = $hash === false ? $path : $path.'?v='.substr($hash, 0, 8);
    }

    /**
     * Drop the memoised URLs, for a process that outlives a file it has hashed.
     */
    public static function flush(): void
    {
        self::$urls = [];
    }
}
