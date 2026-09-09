<?php

namespace App\Actions\Webmentions;

use App\Support\SafeUrl;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Download and store one webmention author's photo, so a face can be shown
 * without every reader's browser fetching an image from a stranger's server.
 *
 * Mirrors StoreFavicon: a plain file at a derived path, skipped when one is
 * already there. The guards are the difference. StoreFavicon only ever talks
 * to Google's API; this fetches whatever an h-card points at, so the URL has
 * to be vetted, the response has to actually be an image, and the download has
 * to be capped.
 */
class StoreAuthorPhoto
{
    private const TIMEOUT_SECONDS = 10;

    /** An avatar renders at 48px, so anything past this is a mistake or an attack. */
    private const MAX_BYTES = 2 * 1024 * 1024;

    /** Twice the largest rendered size, for retina. */
    private const SIZE = 96;

    private const QUALITY = 82;

    /**
     * The stored path relative to the public root, or null when there is
     * nothing worth storing.
     */
    public function __invoke(?string $photoUrl): ?string
    {
        if ($photoUrl === null || ! SafeUrl::fetchable($photoUrl)) {
            return null;
        }

        // Keyed on the photo URL, not the author's host: a multi-author blog
        // publishes several people from one domain.
        $relative = 'avatars/'.hash('sha256', $photoUrl).'.webp';
        $path = public_path($relative);

        if (File::exists($path)) {
            return $relative;
        }

        $body = $this->download($photoUrl);

        if ($body === null) {
            return null;
        }

        File::ensureDirectoryExists(dirname($path));

        return $this->square($body, $path) ? $relative : null;
    }

    private function download(string $url): ?string
    {
        $response = rescue(
            fn () => Http::timeout(self::TIMEOUT_SECONDS)->get($url),
            null,
            report: false,
        );

        if ($response === null || ! $response->successful()) {
            return null;
        }

        if (! str_starts_with((string) $response->header('content-type'), 'image/')) {
            return null;
        }

        $body = $response->body();

        return strlen($body) > self::MAX_BYTES ? null : $body;
    }

    /**
     * Crop to a square and re-encode, which also means the bytes finally served
     * are an image this app produced rather than whatever arrived.
     */
    private function square(string $body, string $path): bool
    {
        $temporary = $path.'.download';

        try {
            File::put($temporary, $body);

            Image::load($temporary)
                ->fit(Fit::Crop, self::SIZE, self::SIZE)
                ->format('webp')
                ->quality(self::QUALITY)
                ->save($path);

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            File::delete($temporary);
        }
    }
}
