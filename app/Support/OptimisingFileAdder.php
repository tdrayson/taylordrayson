<?php

namespace App\Support;

use App\Actions\Media\PrepareImage;
use Spatie\MediaLibrary\MediaCollections\FileAdder;

/**
 * A FileAdder that converts an incoming image to the stored WebP master.
 *
 * Bound in place of Media Library's own adder so the invariant holds at every
 * entry point rather than at the call sites: a Strava photo, a Mapbox render, a
 * Foursquare import and an editor upload all pass through `setFile()`, as will
 * anything added later without knowing this exists.
 */
class OptimisingFileAdder extends FileAdder
{
    /**
     * Gated on mime rather than extension: `addMediaFromString` writes to a
     * `tempnam()` file with no extension at all, so PrepareImage's own check
     * cannot see an SVG coming and would rasterise it.
     *
     * @var list<string>
     */
    private const NOT_CONVERTED = ['image/svg+xml', 'image/gif'];

    /**
     * Extension each stored image format is named with, so a name can be checked
     * against the bytes it actually points at.
     *
     * @var array<string, string>
     */
    private const EXTENSIONS = [
        'image/webp' => 'webp',
        'image/svg+xml' => 'svg',
        'image/gif' => 'gif',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
    ];

    /** The stored file, kept so the name can be aligned to what it holds. */
    private ?string $storedPath = null;

    public function setFile($file): self
    {
        if (is_string($file) && $this->isImage($file)) {
            $file = app(PrepareImage::class)($file);
        }

        if (is_string($file)) {
            $this->storedPath = $file;
        }

        return parent::setFile($file);
    }

    /**
     * Keep the extension honest. Callers name a file for the format they asked
     * the API for, which is wrong twice over: a converted Mapbox render would be
     * stored as `.png`, and a TMDB logo that arrived as SVG was stored as
     * `.webp`, which is served with a content type it cannot be read as.
     */
    public function setFileName(string $fileName): self
    {
        $extension = self::EXTENSIONS[$this->storedMime()] ?? null;

        if ($extension !== null && strtolower(pathinfo($fileName, PATHINFO_EXTENSION)) !== $extension) {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME).'.'.$extension;
        }

        return parent::setFileName($fileName);
    }

    private function storedMime(): ?string
    {
        if ($this->storedPath === null || ! is_file($this->storedPath)) {
            return null;
        }

        $mime = @mime_content_type($this->storedPath);

        return is_string($mime) ? $mime : null;
    }

    private function isImage(string $path): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $mime = @mime_content_type($path);

        return is_string($mime)
            && str_starts_with($mime, 'image/')
            && ! in_array($mime, self::NOT_CONVERTED, true);
    }
}
