<?php

namespace App\Jobs;

use App\Models\WebmentionSend;
use App\Support\OutboundLinks;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\SerializesModels;

/**
 * Works out which sites an entry links to need telling, and queues one
 * SendWebmention per site so a slow one cannot stall the rest.
 *
 * Which links get a send is not "all of them, every save". The spec asks a
 * sender to re-send when the source is updated, including to a URL the link
 * was removed from, and re-sending an unchanged post to the same targets just
 * makes duplicates at the other end. So the decision is:
 *
 *   removed = sent before, not linked now   one last send, so they can drop it
 *   toSend  = changed ? every link : the ones not yet delivered
 *
 * The second half of that is why the sends are stored rather than diffed off
 * the model: without a record there is no way to tell "already told them" from
 * "tried and it failed", and a 5xx would be mistaken for a delivery.
 *
 * Unique per entry, which is what debounces a salmention. A thread that picks
 * up five replies in a minute must not fire five rounds of webmentions at every
 * site the entry links to, and the lock is held until the delayed job runs, so
 * the burst collapses into the one send. The model is re-read at that point, so
 * whatever the lock swallowed is in the payload anyway.
 */
class SendWebmentions implements ShouldBeUnique, ShouldQueue
{
    use Queueable, SerializesModels;

    /**
     * How long a response waits before the world is told, so a conversation
     * settles first. Long enough to swallow a burst, short enough that an
     * upstream author re-fetches while the thread is still live.
     */
    public const RESPONSE_DELAY_MINUTES = 3;

    /** Released when the job runs, so the lock cannot outlive a dead worker. */
    public int $uniqueFor = 900;

    public function __construct(private readonly Model $source) {}

    public function uniqueId(): string
    {
        return $this->source::class.':'.$this->source->getKey();
    }

    public function handle(): void
    {
        // Nowhere but production can be fetched back to verify the mention, so
        // a send from anywhere else is noise on the target's site.
        if (! config('webmentions.send')) {
            return;
        }

        $sourceUrl = rtrim((string) config('app.url'), '/').$this->source->url();
        $links = OutboundLinks::for($this->source);
        $hash = OutboundLinks::fingerprint($this->source);

        $previous = WebmentionSend::query()->where('source_url', $sourceUrl)->get();

        if ($links === [] && $previous->isEmpty()) {
            return;
        }

        $changed = $previous->isEmpty() || $previous->contains(fn (WebmentionSend $send): bool => $send->content_hash !== $hash);
        // A site with no endpoint counts as done, not as pending a retry: it
        // would otherwise be re-probed on every run for as long as it is
        // linked. A later edit re-discovers it, which is soon enough.
        $delivered = $previous->whereIn('status', ['sent', 'unsupported'])->pluck('target_url')->all();

        $targets = array_unique([
            // Everything, when the body changed, so receivers re-fetch it.
            ...($changed ? $links : array_values(array_diff($links, $delivered))),
            // Linked before, not now: one last send, or they keep showing it.
            ...array_values(array_diff($previous->pluck('target_url')->all(), $links)),
        ]);

        foreach ($targets as $target) {
            SendWebmention::dispatch($sourceUrl, $target, $hash, $this->source->getMorphClass(), $this->source->getKey());
        }
    }
}
