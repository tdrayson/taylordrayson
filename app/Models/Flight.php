<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'flight_number',
    'airline_icao',
    'origin_iata',
    'destination_iata',
    'distance_miles',
    'cabin_class',
    'reason',
    'meta',
])]
class Flight extends Model implements Timelineable
{
    use HasAssets;
    use HasFactory;
    use HasTimelineEntry;

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

    public function airline(): BelongsTo
    {
        return $this->belongsTo(Airline::class, 'airline_icao', 'icao_code');
    }

    public function origin(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'origin_iata', 'iata_code');
    }

    public function destination(): BelongsTo
    {
        return $this->belongsTo(Airport::class, 'destination_iata', 'iata_code');
    }

    public function slug(): string
    {
        return strtolower("{$this->origin_iata}-{$this->destination_iata}");
    }

    public function card(): array
    {
        return [
            'type' => 'flight',
            'icon' => 'plane',
            'title' => "{$this->origin_iata} → {$this->destination_iata}",
            'subtitle' => $this->distance_miles ? sprintf('%s mi · %s', number_format($this->distance_miles), $this->cabin_class) : null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'flight',
            'meta' => [],
        ];
    }
}
