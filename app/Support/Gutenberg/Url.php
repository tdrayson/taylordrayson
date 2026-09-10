<?php

namespace App\Support\Gutenberg;

final class Url
{
    /**
     * Percent-encode the bytes a URL cannot carry literally.
     *
     * Media uploaded through WordPress keeps whatever was in the filename, so
     * some of these addresses hold real UTF-8. Browsers encode it on the way
     * out; nothing else here does, and an unencoded byte fails every validator
     * the address later passes through.
     */
    public static function normalise(string $url): string
    {
        return (string) preg_replace_callback(
            '/[^\x21-\x7E]/',
            fn (array $match): string => rawurlencode($match[0]),
            trim($url),
        );
    }
}
