<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
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

    /**
     * @var list<string>
     */
    protected $appends = ['logo_url'];

    /**
     * Public path to the stored brand logo, or null when the brand is unset or
     * no logo file has been downloaded.
     */
    protected function logoUrl(): Attribute
    {
        return Attribute::get(function (): ?string {
            if (! $this->brand) {
                return null;
            }

            $slug = Str::slug($this->brand);

            return file_exists(public_path("logos/brands/{$slug}.png"))
                ? "/logos/brands/{$slug}.png"
                : null;
        });
    }

    public function getVehicleAttribute(): mixed
    {
        return config("vehicles.{$this->vehicle_id}");
    }

    public function slug(): string
    {
        return 'fuel';
    }
}
