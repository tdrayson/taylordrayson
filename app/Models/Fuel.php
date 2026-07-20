<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'occurred_at',
    'vehicle_id',
    'station_name',
    'brand',
    'address',
    'postcode',
    'city',
    'county',
    'country',
    'latitude',
    'longitude',
    'litres',
    'cost',
    'fuel_card_cost',
    'price_per_litre',
    'odometer',
])]
class Fuel extends Model implements HasMedia, Timelineable
{
    use HasAttachments;
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

    public function slug(): string
    {
        return 'fuel';
    }

    public function card(): CardData
    {
        $parts = array_filter([
            sprintf('%sL, £%.2f', $this->litres, $this->cost),
            $this->price_per_litre ? sprintf('£%s / L', number_format($this->price_per_litre, 3)) : null,
        ]);

        return new CardData(
            type: 'fuel',
            icon: 'fuel',
            title: $this->station_name ?? 'Fuel',
            titleLabel: 'Fuel stop'.($this->station_name ? ', '.$this->station_name : ''),
            subtitle: implode(', ', $parts),
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'fuel',
            range: null,
            meta: CardMeta::locationMap(
                map: $this->getFirstMediaUrl('map') ?: null,
                mapDark: $this->getFirstMediaUrl('map_dark') ?: null,
            ),
        );
    }
}
