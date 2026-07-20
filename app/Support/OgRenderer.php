<?php

namespace App\Support;

use Illuminate\View\View;
use Spatie\Browsershot\Browsershot;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Render a Blade view to a 1200x630 OG card PNG via Browsershot, and serve a
 * cached card file back as a response with the right image headers.
 */
final class OgRenderer
{
    /**
     * Screenshot a rendered Blade view to a 1200x630 PNG file via Browsershot.
     */
    public function screenshot(View $view, string $path): void
    {
        $this->browsershot($view)->save($path);
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
            ->setScreenshotType('png');

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
