<?php

namespace App\Jobs;

use App\Services\Tmdb\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * Enriches a newly synced Trakt series or film with TMDB structure (seasons,
 * genres, tagline) and upgrades its artwork from TMDB's poster/backdrop/logo
 * to R2 (Trakt's poster remains the fallback when TMDB has none). Best-effort
 * throughout: a missing id or a failed metadata fetch degrades gracefully,
 * only a failed image download retries the job.
 */
class EnrichMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        private Model&HasMedia $subject,
        private string $kind,
        private ?int $tmdbId,
        private ?string $fallbackPosterUrl,
    ) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(Client $tmdb): void
    {
        $meta = $this->subject->meta->toArray();
        $posterDownloaded = false;

        if ($this->tmdbId !== null) {
            [$meta, $posterDownloaded] = $this->applyTmdb($tmdb, $meta);
        }

        if (! $posterDownloaded && $this->fallbackPosterUrl !== null) {
            $this->downloadImage('cover', $this->fallbackPosterUrl);
        }

        $this->subject->meta = $meta;
        $this->subject->save();
    }

    /**
     * Fetch the TMDB detail + images payloads, merge the `tmdb` (and, for tv,
     * `seasons`/`season_list`) blocks into meta, and download whichever
     * images are available.
     *
     * Works in the stored array spelling rather than the meta DTO, because
     * what it is really doing is translating TMDB's payload; the DTO takes
     * over at the boundary in handle(), where it drops empty keys for both
     * branches (this used to happen for tv only).
     *
     * @param  array<string, mixed>  $meta
     * @return array{0: array<string, mixed>, 1: bool} The updated meta, and whether a poster was downloaded.
     */
    private function applyTmdb(Client $tmdb, array $meta): array
    {
        $detail = $this->kind === 'tv' ? $tmdb->tv($this->tmdbId) : $tmdb->movie($this->tmdbId);

        if ($detail === null) {
            return [$meta, false];
        }

        $meta['tmdb'] = array_filter([
            'id' => $detail['id'] ?? $this->tmdbId,
            'status' => $detail['status'] ?? null,
            'network' => ! empty($detail['networks']) ? ($detail['networks'][0]['name'] ?? null) : null,
            'genres' => ! empty($detail['genres']) ? array_column($detail['genres'], 'name') : null,
            'tagline' => $detail['tagline'] ?? null,
            'vote' => $detail['vote_average'] ?? null,
        ], fn ($value): bool => $value !== null && $value !== [] && $value !== '');

        if ($this->kind === 'tv') {
            // Keep any existing (Trakt-derived) count if TMDB omits number_of_seasons.
            $meta['seasons'] = $detail['number_of_seasons'] ?? ($meta['seasons'] ?? null);
            $meta['season_list'] = collect($detail['seasons'] ?? [])
                ->map(fn (array $season): array => [
                    'number' => $season['season_number'] ?? null,
                    'name' => $season['name'] ?? null,
                    'episode_count' => $season['episode_count'] ?? null,
                    'air_date' => $season['air_date'] ?? null,
                ])
                ->all();

        }

        $posterDownloaded = $this->downloadTmdbImage('cover', $tmdb->imageUrl($detail['poster_path'] ?? null, 'w780'));
        $this->downloadTmdbImage('backdrop', $tmdb->imageUrl($detail['backdrop_path'] ?? null, 'w1280'));

        $logoPath = $this->bestLogoPath($tmdb->images($this->kind, $this->tmdbId));
        $this->downloadTmdbImage('logo', $tmdb->imageUrl($logoPath, 'w500'));

        return [$meta, $posterDownloaded];
    }

    /**
     * Pick the best logo from TMDB's `images.logos[]`: prefer an English or
     * language-agnostic entry, otherwise take the first one available.
     *
     * @param  array<string, mixed>|null  $images
     */
    private function bestLogoPath(?array $images): ?string
    {
        $logos = $images['logos'] ?? [];

        if ($logos === []) {
            return null;
        }

        $preferred = collect($logos)->first(fn (array $logo): bool => in_array($logo['iso_639_1'] ?? null, [null, 'en'], true));

        return ($preferred ?? $logos[0])['file_path'] ?? null;
    }

    /**
     * Download a TMDB-hosted image into the given collection. Best-effort:
     * a null url (missing path) or a failed fetch is skipped rather than
     * thrown, since TMDB artwork is enrichment, not the source of truth.
     */
    private function downloadTmdbImage(string $collection, ?string $url): bool
    {
        if ($url === null) {
            return false;
        }

        try {
            $this->downloadImage($collection, $url);

            return true;
        } catch (\RuntimeException) {
            return false;
        }
    }

    /**
     * Download an image into a media collection, clearing it first so the
     * collection stays single-image even across retries or re-syncs. Throws
     * on a failed HTTP fetch so the queue retries.
     */
    private function downloadImage(string $collection, string $url): void
    {
        $url = Str::startsWith($url, 'http') ? $url : 'https://'.$url;
        $response = Http::get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Image download failed: {$url} ({$response->status()})");
        }

        $this->subject->clearMediaCollection($collection);
        $this->subject->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.webp')
            ->toMediaCollection($collection);
    }
}
