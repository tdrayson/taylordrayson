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
 * Mirrors one episode's audio and artwork off the publisher and into local
 * storage, so the site stops depending on thisweekwith.co.uk staying up (and on
 * its URLs never changing) in order to play its own back catalogue.
 *
 * Queued because an episode is a 27-55MB download: far too slow to do inline
 * during a sync, and worth retrying rather than skipping when it fails.
 */
class StorePodcastMedia implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * Generous because the work is bounded by bandwidth, not by our own code:
     * the largest episodes are ~55MB, and a slow connection should be waited
     * out rather than have the job killed halfway and started again.
     */
    public int $timeout = 900;

    public function __construct(private Podcast $podcast, private bool $force = false) {}

    /**
     * Never two runs for the same episode at once.
     *
     * The queue's `retry_after` is 90 seconds, comfortably shorter than a large
     * download takes, so a worker will release this job back onto the queue
     * while the first attempt is still going. Without this the same 55MB would
     * be pulled twice over and stored twice.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->podcast->id))->dontRelease()];
    }

    public function handle(): void
    {
        $this->store('audio', $this->podcast->audio_url, 'mp3');
        $this->store('cover', $this->podcast->cover_image);
        $this->store('artwork', $this->podcast->thumbnail);
    }

    /**
     * Download one file into one single-file collection, leaving an existing copy
     * alone unless forced. Streamed to a temporary file rather than held in memory;
     * addMedia() moves it, so there is nothing to clean up.
     */
    private function store(string $collection, ?string $url, ?string $fallbackExtension = null): void
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

        $extension = pathinfo(parse_url($url, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION) ?: $fallbackExtension;

        // Named after the episode rather than after the publisher's file, so a
        // stored copy says which episode it is without a database lookup.
        $filename = $this->podcast->slug().($extension ? ".{$extension}" : '');

        $this->podcast->clearMediaCollection($collection);
        $this->podcast->addMedia($temporaryFile)->usingFileName($filename)->toMediaCollection($collection);
    }
}
