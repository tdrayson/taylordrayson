<?php

namespace App\Http\Controllers;

use App\Actions\Og\BuildEntryOgData;
use App\Actions\Og\BuildPageOgData;
use App\Actions\Og\OgGalleryUrls;
use App\Datasets\Datasets;
use App\Models\Scopes\ListedScope;
use App\Models\TimelineEntry;
use App\Support\OgRenderer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OgImageController extends Controller
{
    public function __construct(
        private readonly BuildEntryOgData $entryOgData,
        private readonly BuildPageOgData $pageOgData,
        private readonly OgGalleryUrls $galleryUrls,
        private readonly OgRenderer $renderer,
    ) {}

    /**
     * Render (and cache) a page's 1200x630 Open Graph card. Only the signed URL
     * OgMeta emits is served, and `for` names the page so its old cards go.
     */
    public function show(Request $request): BinaryFileResponse
    {
        abort_unless($request->hasValidSignature(), 404);

        $card = ($this->pageOgData)(
            $request->query('title'),
            $request->query('eyebrow'),
            $request->query('accent'),
            $request->query('variant'),
            $request->query('description'),
        );

        $path = $this->renderer->card(
            'page',
            md5((string) $request->query('for')),
            md5(implode('|', [$card['layout'], $card['title'], (string) $card['eyebrow'], $card['accent'], (string) $card['subtitle']])),
            fn (): View => view('og.card', [
                ...$card,
                'cutout' => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png'),
            ]),
        );

        return $this->renderer->serve($path, 'public, max-age=31536000, immutable');
    }

    /**
     * Render (and cache) the Open Graph card for a single timeline entry, built
     * from the entry's real data: type accent, eyebrow, title, date, and a
     * contextual image (route map, check-in marker, or cover) where one fits.
     *
     * Cached by entry id plus the model's last-updated stamp, keeping only the
     * latest. An unlisted or private entry is served only on the signed URL its
     * own page emits, and 404s otherwise.
     */
    public function entry(Request $request, int $entry): BinaryFileResponse
    {
        $entry = TimelineEntry::query()
            ->when($request->hasValidSignature(), fn (Builder $query): Builder => $query->withoutGlobalScope(ListedScope::class))
            ->findOrFail($entry);

        $card = ($this->entryOgData)($entry, fn (): string => $this->galleryUrls->dataUri('taylor-cutout.png', 'image/png'));

        abort_if($card === null, 404);

        $path = $this->renderer->card(
            'entry',
            (string) $entry->id,
            (string) BuildEntryOgData::entryTimestamp($entry),
            fn (): View => view('og.card', $card),
        );

        return $this->renderer->serve($path, 'public, max-age=86400');
    }

    /**
     * TEMP: render (and cache) one sample card per data type for the gallery.
     * Cached by card signature, so editing the template is enough to refresh
     * after a design tweak.
     */
    public function preview(string $type): BinaryFileResponse
    {
        $type = Datasets::for($type)?->type()->value;
        $cards = $this->galleryUrls->sampleCards();

        abort_unless($type !== null && isset($cards[$type]), 404);

        $disk = Storage::disk('local');
        $directory = 'og/'.OgRenderer::generation().'/preview';
        $path = $directory.'/'.md5($type).'.png';

        if (! $disk->exists($path)) {
            $disk->makeDirectory($directory);

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
