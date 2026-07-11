<?php

namespace App\Jobs;

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
 * Downloads a Trakt/TMDB poster and caches it in the subject's `cover`
 * collection on R2. Trakt forbids hotlinking, so the image must live in our
 * own storage. Clears the collection first so a re-sync stays idempotent.
 */
class FetchTraktPoster implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private Model&HasMedia $subject, private string $posterUrl) {}

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(): void
    {
        $url = Str::startsWith($this->posterUrl, 'http') ? $this->posterUrl : 'https://'.$this->posterUrl;
        $response = Http::get($url);

        if ($response->failed()) {
            throw new \RuntimeException("Poster download failed: {$url} ({$response->status()})");
        }

        $this->subject->clearMediaCollection('cover');
        $this->subject->addMediaFromString($response->body())
            ->usingFileName(Str::uuid().'.webp')
            ->toMediaCollection('cover');
    }
}
