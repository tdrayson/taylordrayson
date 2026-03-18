<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'flight_number',
    'airline_iata',
    'origin_iata',
    'destination_iata',
    'origin_latitude',
    'origin_longitude',
    'destination_latitude',
    'destination_longitude',
    'distance_miles',
    'cabin_class',
    'reason',
    'meta',
])]
class Flight extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
            'meta' => 'array',
        ];
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        return [
            'type' => 'flight',
            'icon' => 'plane',
            'title' => "{$this->origin_iata} → {$this->destination_iata}",
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'flight',
            'meta' => [],
        ];
    }
}
