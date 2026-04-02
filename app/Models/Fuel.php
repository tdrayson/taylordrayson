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
    'vehicle_id',
    'litres',
    'cost',
    'fuel_card_cost',
    'price_per_litre',
    'odometer',
    'station',
    'city',
])]
class Fuel extends Model implements Timelineable
{
    use HasAssets;
    use HasFactory;
    use HasTimelineEntry;

    protected $table = 'fuel';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'occurred_at' => 'datetime',
        ];
    }

    public function getVehicleAttribute(): mixed
    {
        return config("vehicles.{$this->vehicle_id}");
    }

    /**
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        return [
            'type' => 'fuel',
            'icon' => 'fuel',
            'title' => sprintf("%sL · £%.2f", $this->litres, $this->cost),
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'fuel',
            'meta' => [],
        ];
    }
}
