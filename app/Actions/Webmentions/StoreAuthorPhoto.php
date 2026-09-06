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
     *
     * With $refresh the file is fetched again even though it is already there,
     * which is how a changed avatar is picked up. A failed refresh keeps what
     * is on disk: an unreachable server should not cost us the face we have.
     */
    public function __invoke(?string $photoUrl, bool $refresh = false): ?string
    {
        if ($photoUrl === null || ! SafeUrl::fetchable($photoUrl)) {
            return null;
        }

        // Keyed on the photo URL, not the author's host: a multi-author blog
        // publishes several people from one domain.
        $relative = 'avatars/'.hash('sha256', $photoUrl).'.webp';
        $path = public_path($relative);
        $held = File::exists($path);

        if ($held && ! $refresh) {
            return $relative;
        }

        $body = $this->download($photoUrl);

        if ($body === null) {
            return $held ? $relative : null;
        }

        File::ensureDirectoryExists(dirname($path));

        if ($this->square($body, $path)) {
            return $relative;
        }

        return $held ? $relative : null;
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
     *
     * Encoded beside the target and moved into place, so a refresh that fails
     * halfway leaves the previous file whole rather than truncated.
     */
    private function square(string $body, string $path): bool
    {
        $temporary = $path.'.download';
        $encoded = $path.'.encoding';

        try {
            File::put($temporary, $body);

            Image::load($temporary)
                ->fit(Fit::Crop, self::SIZE, self::SIZE)
                ->format('webp')
                ->quality(self::QUALITY)
                ->save($encoded);

            File::move($encoded, $path);

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            File::delete($temporary);
            File::delete($encoded);
        }
    }
}
