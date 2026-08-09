<?php

namespace App\Jobs;

use App\Actions\GenerateFlightMap;
use App\Actions\GenerateLocationMap;
use App\Actions\GenerateStaticMap;
use App\Models\Activity;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Support\TypeColors;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Spatie\MediaLibrary\HasMedia;

/**
 * Draws the static map for a newly created entry: an activity's route, a
 * flight's arc, or a pin for anything with coordinates.
 *
 * Queued rather than done inline because it is two Mapbox fetches that the
 * entry does not need in order to exist, and because a failed fetch should be
 * retried rather than skipped. Dispatched from the points where a single entry
 * is created (a sync run, a manual entry), never from a bulk import, which
 * would queue a job per row and hammer Mapbox to redraw maps that already
 * exist; those are still covered by the maps:generate sweep.
 */
class GenerateEntryMap implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(private Model&HasMedia $entry) {}

    /**
     * Seconds to wait before each retry. Mapbox failures are usually a blip or
     * a rate limit, so the first retry is quick and the second gives it longer.
     *
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(GenerateLocationMap $pin, GenerateStaticMap $route, GenerateFlightMap $arc): void
    {
        // Already mapped: a redispatch (or a retry that got further than it
        // reported) should not spend two more Mapbox calls redrawing it.
        if ($this->entry->getFirstMedia('map')) {
            return;
        }

        match (true) {
            $this->entry instanceof Activity => $route($this->entry),
            $this->entry instanceof Flight => $arc($this->entry),
            $this->entry instanceof Fuel => $pin($this->entry, TypeColors::hex('fuel')),
            $this->entry instanceof Checkin => $pin($this->entry, TypeColors::hex('checkin')),
            default => null,
        };
    }
}
