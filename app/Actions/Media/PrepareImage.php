<?php

namespace App\Actions\Media;

use Illuminate\Support\Facades\File;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;
use Throwable;

/**
 * Bring an image down to something worth storing before it becomes an
 * attachment: capped on the long edge and re-encoded as WebP.
 *
 * Media Library's conversions do not touch the original, so without this the
 * full-size file is kept exactly as it arrived, which for a phone photo is
 * several megabytes of something nothing ever serves.
 *
 * Runs on every path an image can arrive by, including the ones no browser is
 * involved in: an API post, or an image pulled from Strava or a podcast feed.
 */
class PrepareImage
{
    /** The long edge kept, matching the `full` conversion in HasAttachments. */
    private const MAX_DIMENSION = 1920;

    private const QUALITY = 82;

    /**
     * Formats left exactly as they arrived. Rasterising vector SVG is a
     * downgrade and Imagick flattens an animated GIF to one frame, which is the
     * same reason HasAttachments skips converting them.
     *
     * @var list<string>
     */
    private const UNTOUCHED = ['svg', 'gif'];

    /**
     * Rewrite the file in place where it is worth it, returning the path to use.
     * The original path comes back unchanged when the image is already small
     * enough, is a format left alone, or could not be read.
     */
    public function __invoke(string $path): string
    {
        if (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::UNTOUCHED, true)) {
            return $path;
        }

        if ($this->alreadyPrepared($path)) {
            return $path;
        }

        $target = pathinfo($path, PATHINFO_DIRNAME).'/'.pathinfo($path, PATHINFO_FILENAME).'.webp';

        try {
            Image::load($path)
                ->fit(Fit::Max, self::MAX_DIMENSION, self::MAX_DIMENSION)
                ->format('webp')
                ->quality(self::QUALITY)
                ->save($target);
        } catch (Throwable) {
            // A file Imagick cannot read is stored as it came rather than
            // failing the upload: better a large original than a lost one.
            return $path;
        }

        // Re-encoding is not always a win. A small PNG screenshot can come out
        // bigger as WebP, and there is no sense keeping the worse of the two.
        if (! File::exists($target) || ($target !== $path && File::size($target) >= File::size($path))) {
            if ($target !== $path) {
                File::delete($target);
            }

            return $path;
        }

        if ($target !== $path) {
            File::delete($path);
        }

        return $target;
    }

    /**
     * Whether the file is already what this action would produce. The editor
     * shrinks and re-encodes in the browser, so without this the happy path
     * pays a second lossy pass that only loses quality.
     */
    private function alreadyPrepared(string $path): bool
    {
        if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'webp') {
            return false;
        }

        try {
            $image = Image::load($path);
        } catch (Throwable) {
            return false;
        }

        return max($image->getWidth(), $image->getHeight()) <= self::MAX_DIMENSION;
    }
}
