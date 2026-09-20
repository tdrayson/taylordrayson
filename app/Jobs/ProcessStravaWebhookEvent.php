<?php

namespace App\Jobs;

use App\Actions\Strava\StoreStravaActivity;
use App\Data\StravaWebhookEvent;
use App\Enums\EntryStatus;
use App\Enums\Source;
use App\Enums\StravaAspect;
use App\Enums\StravaObjectType;
use App\Models\Activity;
use App\Services\Pushover\Client as Pushover;
use App\Services\Strava\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Act on one pushed Strava event, off the request that carried it.
 *
 * The payload names an activity rather than carrying it, so this is where the
 * single read is spent. Strava routinely pushes `create` before the activity is
 * readable, so a missing detail is a retry rather than a failure.
 */
class ProcessStravaWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300, 900];

    public function __construct(public StravaWebhookEvent $event) {}

    public function handle(Client $strava, StoreStravaActivity $store): void
    {
        Log::info('strava webhook event', $this->event->toArray());

        if ($this->event->objectType === StravaObjectType::Athlete) {
            $this->handleAthlete();

            return;
        }

        if ($this->event->objectType !== StravaObjectType::Activity || ! $this->isMine()) {
            return;
        }

        if ($this->event->aspect === StravaAspect::Delete) {
            $this->hide();

            return;
        }

        if ($this->event->aspect === null) {
            return;
        }

        $detail = $strava->activity($this->event->objectId);

        // Strava pushes `create` before the upload has finished processing, so
        // the first read often 404s. Backing off and asking again is the only
        // fix; giving up here would silently lose the activity.
        if ($detail === null) {
            $this->release($this->backoff[$this->attempts() - 1] ?? 900);

            return;
        }

        $store($detail);
    }

    /**
     * Athlete events are deauthorisation in practice, and a revoked app stops
     * syncing without any other symptom, so it goes to the phone.
     */
    private function handleAthlete(): void
    {
        if (! $this->event->isDeauthorisation()) {
            return;
        }

        Log::warning('strava access revoked', $this->event->toArray());

        rescue(fn () => app(Pushover::class)->send(
            'Strava access revoked',
            'Strava says the app has been deauthorised. Activity syncing has stopped until it is reconnected.',
        ), report: false);
    }

    /**
     * An activity deleted on Strava goes private rather than away, so a mistaken
     * delete does not take its photos and map with it. This also covers the
     * ambiguity in the event itself: on `activity:read` scope Strava sends
     * `delete` when an activity merely turns private, and `create` when it
     * turns back.
     */
    private function hide(): void
    {
        $activity = Activity::query()
            ->where('source', Source::Strava->value)
            ->where('source_id', $this->event->objectId)
            ->first();

        $activity?->update(['status' => EntryStatus::Private]);
    }

    /** Events for another athlete cost nothing to drop and a read to honour. */
    private function isMine(): bool
    {
        $athlete = config('services.strava.athlete_id');

        return blank($athlete) || (string) $athlete === $this->event->ownerId;
    }
}
