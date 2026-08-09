<?php

namespace App\Http\Controllers;

use App\Actions\Og\BuildEntryOgData;
use App\Actions\Og\OgGalleryUrls;
use App\Models\TimelineEntry;
use App\Support\OgRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OgImageController extends Controller
{
    private const TAGLINE = 'A living archive of everything I make, watch, read, and get up to.';

    public function __construct(
        private readonly BuildEntryOgData $entryOgData,
        private readonly OgGalleryUrls $galleryUrls,
        private readonly OgRenderer $renderer,
    ) {}

    /**
     * Render (and cache) a 1200x630 Open Graph card for the given title.
     *
     * Input is read leniently so a malformed share URL still returns a valid
     * default card rather than an error. Cards are cached by a hash of their
     * inputs and regenerated only when missing.
     */
    public function show(Request $request): BinaryFileResponse
    {
        $title = Str::limit(trim((string) $request->query('title')) ?: 'Taylor Drayson', 160, '');
        $eyebrow = Str::limit(trim((string) $request->query('eyebrow')), 60, '') ?: null;
        $date = Str::limit(trim((string) $request->query('date')), 60, '') ?: null;
        $accent = $this->galleryUrls->accent($request->query('accent'));
        $layout = $request->query('variant') === 'home' ? 'home' : 'text';

        $disk = Storage::disk('local');
        $path = 'og/'.md5(implode('|', [config('og.version'), $layout, $title, (string) $eyebrow, (string) $date, $accent])).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og');

            $card = [
                'layout' => $layout,
                'accent' => $accent,
                'eyebrow' => $eyebrow,
                'title' => $title,
                'date' => $date,
                'subtitle' => $layout === 'home' ? self::TAGLINE : null,
                'image' => null,
                'cutout' => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png'),
            ];

            $this->renderer->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->renderer->serve($disk->path($path), 'public, max-age=31536000, immutable');
    }

    /**
     * Render (and cache) the Open Graph card for a single timeline entry, built
     * from the entry's real data: type accent, eyebrow, title, date, and a
     * contextual image (route map, check-in marker, or cover) where one fits.
     *
     * Cached by entry id plus the model's last-updated stamp, so the same URL is
     * reused until the entry changes.
     */
    public function entry(TimelineEntry $entry): BinaryFileResponse
    {
        $card = ($this->entryOgData)($entry, fn (): string => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png'));

        abort_if($card === null, 404);

        $disk = Storage::disk('local');
        $path = 'og/entry/'.md5(implode('|', [config('og.version'), $entry->id, $this->entryOgData->entryTimestamp($entry)])).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og/entry');
            $this->renderer->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->renderer->serve($disk->path($path), 'public, max-age=86400');
    }

    /**
     * TEMP: render (and cache) one sample card per data type for the gallery.
     * Cached by og version, so bump OG_VERSION (or run `og:clear`) to refresh
     * after a design tweak.
     */
    public function preview(string $type): BinaryFileResponse
    {
        $cards = $this->galleryUrls->sampleCards();

        abort_unless(isset($cards[$type]), 404);

        $disk = Storage::disk('local');
        $path = 'og/preview/'.md5(config('og.version').'|'.$type).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory('og/preview');

            $card = array_merge($cards[$type], [
                'cutout' => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png'),
            ]);

            $this->renderer->screenshot(view('og.card', $card), $disk->path($path));
        }

        return $this->renderer->serve($disk->path($path), 'public, max-age=86400');
    }

    /**
     * A gallery page listing every OG card variation. Each card is referenced by
     * its real route as a lazily-loaded image, so it renders in its own cached
     * request rather than blocking one giant page render.
     */
    public function gallery(): View
    {
        return view('og.gallery', ['sections' => $this->galleryUrls->sections()]);
    }
}
