<?php

namespace App\Jobs;

use App\Models\Podcast;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Mirrors one episode's artwork off the publisher and into local storage, so the
 * site renders its own images rather than hotlinking thisweekwith.co.uk.
 *
 * Audio is deliberately not mirrored: it is served from the publisher's URL,
 * which is our own, so a second ~10GB copy would buy nothing.
 */
class StorePodcastMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 120;

    public function __construct(private Podcast $podcast, private bool $force = false) {}

    /**
     * Never two runs for the same episode at once, so a job released back onto
     * the queue mid-download cannot store the same image twice.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->podcast->id))->dontRelease()];
    }

    public function handle(): void
    {
        $this->store('cover', $this->podcast->cover_image);
        $this->store('artwork', $this->podcast->thumbnail);
    }

    /**
     * Download one file into one single-file collection, leaving an existing copy
     * alone unless forced. Streamed to a temporary file rather than held in memory;
     * addMedia() moves it, so there is nothing to clean up.
     */
    private function store(string $collection, ?string $url): void
    {
        if (! $url || ($this->podcast->getFirstMedia($collection) && ! $this->force)) {
            return;
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'tww');

        try {
            $response = Http::timeout(600)->sink($temporaryFile)->get($url);
        } catch (ConnectionException $exception) {
            @unlink($temporaryFile);

            throw new RuntimeException("Could not reach {$url} for episode #{$this->podcast->id}.", previous: $exception);
        }

        if ($response->failed()) {
            @unlink($temporaryFile);

            throw new RuntimeException("Got {$response->status()} fetching {$url} for episode #{$this->podcast->id}.");
        }

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION);

        // Named after the episode rather than after the publisher's file, so a
        // stored copy says which episode it is without a database lookup.
        $filename = $this->podcast->slug().($extension ? ".{$extension}" : '');

        $this->podcast->clearMediaCollection($collection);
        $this->podcast->addMedia($temporaryFile)->usingFileName($filename)->toMediaCollection($collection);
    }
}
