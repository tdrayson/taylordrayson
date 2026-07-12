<?php

namespace App\Models;

use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasFlatFile;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\TimelineEntryObserver;
use Database\Factories\FuelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Schema\Blueprint;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(TimelineEntryObserver::class)]
#[Fillable([
    'ulid',
    'occurred_at',
    'vehicle_id',
    'litres',
    'cost',
    'fuel_card_cost',
    'price_per_litre',
    'odometer',
    'fuel_station_id',
    'timezone',
])]
class Fuel extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<FuelFactory> */
    use HasAttachments;

    use HasFactory;
    use HasFlatFile;
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

    public static function schema(Blueprint $table): void
    {
        $table->id();
        $table->ulid('ulid')->nullable()->unique();
        $table->timestamp('occurred_at')->index();
        $table->string('timezone')->nullable();
        $table->string('vehicle_id');
        $table->decimal('litres', 8, 2)->nullable();
        $table->decimal('cost', 8, 2)->nullable();
        $table->decimal('fuel_card_cost', 8, 2)->nullable();
        $table->decimal('price_per_litre', 8, 3)->nullable();
        $table->integer('odometer')->nullable();
        $table->unsignedBigInteger('fuel_station_id')->nullable();
        $table->timestamps();
    }

    public function fuelStation(): BelongsTo
    {
        return $this->belongsTo(FuelStation::class);
    }

    public function getVehicleAttribute(): mixed
    {
        return config("vehicles.{$this->vehicle_id}");
    }

    public function slug(): string
    {
        return 'fuel';
    }

    public function flatFileType(): string
    {
        return 'fuel';
    }

    /**
     * @return list<string>
     */
    public function flatFilePathAttributes(): array
    {
        return ['occurred_at'];
    }

    public function flatFileBaseSlug(bool $original = false): string
    {
        return 'fuel';
    }

    public function flatFileBody(): string
    {
        return '';
    }

    /**
     * @return array<string, mixed>
     */
    public function flatFileMeta(): array
    {
        $attributes = $this->getAttributes();

        return [
            'vehicle_id' => $attributes['vehicle_id'] ?? null,
            'litres' => $attributes['litres'] ?? null,
            'cost' => $attributes['cost'] ?? null,
            'fuel_card_cost' => $attributes['fuel_card_cost'] ?? null,
            'price_per_litre' => $attributes['price_per_litre'] ?? null,
            'odometer' => $attributes['odometer'] ?? null,
            'fuel_station_id' => $attributes['fuel_station_id'] ?? null,
        ];
    }

    public function card(): array
    {
        $parts = array_filter([
            sprintf('%sL, £%.2f', $this->litres, $this->cost),
            $this->price_per_litre ? sprintf('£%s / L', number_format($this->price_per_litre, 3)) : null,
        ]);

        return [
            'type' => 'fuel',
            'icon' => 'fuel',
            'title' => ($this->relationLoaded('fuelStation') ? $this->fuelStation?->name : null) ?? 'Fuel',
            'titleLabel' => 'Fuel stop'.(($station = ($this->relationLoaded('fuelStation') ? $this->fuelStation?->name : null)) ? ', '.$station : ''),
            'subtitle' => implode(', ', $parts),
            'occurred_at' => $this->occurred_at,
            'accent' => 'fuel',
            'meta' => [],
        ];
    }
}
