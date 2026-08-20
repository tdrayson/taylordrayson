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

    /** Whether this adder rewrote the file, so the stored name must follow it. */
    private bool $converted = false;

    public function setFile($file): self
    {
        if (is_string($file) && $this->isImage($file)) {
            $prepared = app(PrepareImage::class)($file);

            if ($prepared !== $file) {
                $this->converted = true;
                $file = $prepared;
            }
        }

        return parent::setFile($file);
    }

    /**
     * Keep the extension honest. Callers name the file for the format they
     * requested from the API, so without this a converted Mapbox render is
     * stored as `.png` while holding WebP bytes.
     */
    public function setFileName(string $fileName): self
    {
        if ($this->converted) {
            $fileName = pathinfo($fileName, PATHINFO_FILENAME).'.webp';
        }

        return parent::setFileName($fileName);
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
