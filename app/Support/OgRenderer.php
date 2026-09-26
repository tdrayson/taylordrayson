<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Render a Blade view to a 1200x630 OG card PNG via Browsershot, and serve a
 * cached card file back as a response with the right image headers.
 *
 * Not final so tests can stand in for Browsershot.
 */
class OgRenderer
{
    /**
     * Screenshot a rendered Blade view to a 1200x630 PNG file via Browsershot.
     */
    public function screenshot(View $view, string $path): void
    {
        $this->browsershot($view)->save($path);
    }

    /**
     * Screenshot a rendered Blade view to 1200x630 PNG bytes, writing nothing to disk.
     */
    public function png(View $view): string
    {
        return $this->browsershot($view)->screenshot();
    }

    /**
     * The token identifying the current card design: the configured version
     * plus a digest of the card template. Names the directory the cards are
     * rendered into, and rides on every card URL as `v` so a design change
     * moves the address rather than only the file behind it.
     *
     * Cards are served `immutable`, so a design change is invisible until the
     * path moves. Leaving that to a hand-bumped OG_VERSION meant the home card
     * shipped a tagline that production never showed; hashing the template
     * makes editing it enough.
     *
     * It is a directory rather than part of each filename so that a superseded
     * design is one identifiable thing. Cards are keyed by a hash of their
     * inputs, which cannot be reversed, so with everything in one folder there
     * was no way to tell a live card from a dead one and the only cleanup
     * available was deleting the lot.
     */
    public static function generation(): string
    {
        static $generation = null;

        return $generation ??= substr(md5(
            config('og.version').'|'.md5_file(resource_path('views/og/card.blade.php')),
        ), 0, 12);
    }

    /**
     * The absolute path of an owner's current card, rendered on a miss, after
     * which the owner's superseded versions are deleted so each keeps one card.
     *
     * @param  string  $family  The card family under the generation, e.g. "entry" or "page".
     * @param  string  $owner  What the card belongs to, free of "-" and glob characters.
     * @param  string  $version  Changes whenever the card's content does.
     * @param  Closure(): View  $view  Builds the card, only called on a miss.
     */
    public function card(string $family, string $owner, string $version, Closure $view): string
    {
        $disk = Storage::disk('local');
        $prefix = 'og/'.self::generation()."/{$family}/{$owner}-";
        $path = $disk->path("{$prefix}{$version}.png");

        if (! is_file($path)) {
            File::ensureDirectoryExists(dirname($path));
            $this->screenshot($view(), $path);

            File::delete(array_diff(glob($disk->path($prefix).'*.png') ?: [], [$path]));
        }

        return $path;
    }

    public function serve(string $path, string $cacheControl): BinaryFileResponse
    {
        return response()->file($path, [
            'Content-Type' => 'image/png',
            'Cache-Control' => $cacheControl,
        ]);
    }

    /**
     * A Browsershot instance configured for a 1200x630 card, with any custom
     * node/chrome binaries applied.
     */
    private function browsershot(View $view): Browsershot
    {
        $browsershot = Browsershot::html($view->render())
            ->windowSize(1200, 630)
            ->waitUntilNetworkIdle()
            ->setScreenshotType('png')
            // Chrome's sandbox needs a new namespace, which php-fpm is not
            // permitted to create, so it dies on launch. What we screenshot is
            // our own Blade view rather than anything a visitor supplies.
            ->noSandbox();

        if ($nodeBinary = config('browsershot.node_binary')) {
            $browsershot->setNodeBinary($nodeBinary);
        }

        if ($npmBinary = config('browsershot.npm_binary')) {
            $browsershot->setNpmBinary($npmBinary);
        }

        if ($chromePath = config('browsershot.chrome_path')) {
            $browsershot->setChromePath($chromePath);
        }

        return $browsershot;
    }
}
