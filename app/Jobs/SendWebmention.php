<?php

namespace App\Jobs;

use App\Models\WebmentionSend;
use App\Support\WebmentionEndpoint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;

/**
 * Sends one webmention, from one of our URLs to one target, and records the outcome.
 * Takes plain values rather than the model so it can still run once the source is gone.
 */
class SendWebmention implements ShouldQueue
{
    use Queueable;

    private const TIMEOUT_SECONDS = 15;

    /**
     * @param  string  $hash  OutboundLinks fingerprint of the source at planning time.
     * @param  string|null  $sourceType  Morph alias of the source entry, null when it no longer exists.
     */
    public function __construct(
        public readonly string $sourceUrl,
        public readonly string $target,
        public readonly string $hash,
        public readonly ?string $sourceType = null,
        public readonly int|string|null $sourceId = null,
    ) {}

    public function handle(): void
    {
        $endpoint = WebmentionEndpoint::discover($this->target);

        $send = WebmentionSend::query()->firstOrNew([
            'source_url' => $this->sourceUrl,
            'target_url' => $this->target,
        ]);

        $send->source_type = $this->sourceType;
        $send->source_id = $this->sourceId;
        $send->endpoint = $endpoint;
        $send->attempts = (int) $send->attempts + 1;
        $send->last_sent_at = now();
        $send->content_hash = $this->hash;

        if ($endpoint === null) {
            // Not a failure to retry: the target simply does not take them.
            $send->status = 'unsupported';
            $send->save();

            return;
        }

        $response = rescue(
            fn () => Http::timeout(self::TIMEOUT_SECONDS)
                ->asForm()
                ->post($endpoint, ['source' => $this->sourceUrl, 'target' => $this->target]),
            null,
            report: false,
        );

        $send->status_code = $response?->status();

        // Anything but a 2xx stays un-delivered, so the next run picks it up
        // rather than the attempt being mistaken for a success.
        $send->status = $response !== null && $response->successful() ? 'sent' : 'failed';
        $send->save();
    }
}
