<?php

namespace App\Actions\Webmentions;

use App\Support\SafeFetch;
use Illuminate\Support\Facades\File;
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
     * What the last call did, for a caller that wants to report it. Written on
     * every invocation and read straight after, which is all the refresh
     * command needs and is why this is a property rather than a return type the
     * verify job would have to unpack.
     *
     * @var 'stored'|'unchanged'|'skipped'|'failed'
     */
    public string $outcome = 'skipped';

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
        $this->outcome = 'failed';

        // SafeFetch validates the URL and every hop it leads to.
        if ($photoUrl === null) {
            return null;
        }

        // Keyed on the photo URL, not the author's host: a multi-author blog
        // publishes several people from one domain.
        $relative = 'avatars/'.hash('sha256', $photoUrl).'.webp';
        $path = public_path($relative);
        $held = File::exists($path);

        if ($held && ! $refresh) {
            $this->outcome = 'skipped';

            return $relative;
        }

        $body = $this->download($photoUrl, $held ? $this->storedValidator($photoUrl) : null);

        // Unchanged since we last asked, or unreachable. Either way the file we
        // hold is the answer, and nothing was transferred.
        if ($body === null) {
            $this->outcome = $held ? 'unchanged' : 'failed';

            return $held ? $relative : null;
        }

        File::ensureDirectoryExists(dirname($path));

        if ($this->square($body, $path)) {
            $this->outcome = 'stored';

            return $relative;
        }

        $this->outcome = $held ? 'unchanged' : 'failed';

        return $held ? $relative : null;
    }

    /**
     * The image bytes, or null when there is nothing new to store.
     *
     * $validator is the ETag or Last-Modified we were given last time. Sending
     * it back turns an unchanged image into a 304 with no body, which costs the
     * other server a header rather than a file. A refresh that finds nothing
     * changed should be close to free for both ends.
     */
    private function download(string $url, ?array $validator): ?string
    {
        // Through SafeFetch, so a photo URL that redirects into the private
        // network is refused at every hop rather than only the first, and the
        // ceiling is applied while reading rather than after.
        $body = SafeFetch::body(
            $url,
            self::MAX_BYTES,
            self::TIMEOUT_SECONDS,
            ($validator ?? []) + ['Accept' => 'image/*'],
            $status,
            $headers,
        );

        if ($body === null || ! str_starts_with((string) ($headers['content-type'] ?? ''), 'image/')) {
            return null;
        }

        $this->rememberValidator($url, $headers);

        return $body;
    }

    /**
     * Where the ETag for a photo is kept. Beside the image would be simpler,
     * but public/avatars is served to the web and holds images only.
     */
    private function validatorPath(string $url): string
    {
        return storage_path('app/avatar-validators/'.hash('sha256', $url).'.json');
    }

    /** The conditional headers for a photo we already hold, if we kept any. */
    private function storedValidator(string $url): array
    {
        $stored = rescue(fn () => File::get($this->validatorPath($url)), null, report: false);
        $decoded = $stored === null ? null : json_decode($stored, true);

        if (! is_array($decoded)) {
            return [];
        }

        return array_filter([
            'If-None-Match' => $decoded['etag'] ?? null,
            'If-Modified-Since' => $decoded['modified'] ?? null,
        ]);
    }

    /**
     * Keep whatever the server gave us to ask with next time.
     *
     * @param  array<string, string>  $headers
     */
    private function rememberValidator(string $url, array $headers): void
    {
        $etag = $headers['etag'] ?? null;
        $modified = $headers['last-modified'] ?? null;

        if ($etag === null && $modified === null) {
            return;
        }

        $path = $this->validatorPath($url);

        File::ensureDirectoryExists(dirname($path));
        File::put($path, json_encode(array_filter(['etag' => $etag, 'modified' => $modified])));
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
