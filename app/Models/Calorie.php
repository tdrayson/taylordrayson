<?php

namespace App\Models;

use App\Content\CalorieDayFileSynchronizer;
use App\Contracts\DefinesContentSchema;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\CalorieTimelineObserver;
use Database\Factories\CalorieFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(CalorieTimelineObserver::class)]
#[Fillable([
    'ulid',
    'occurred_at',
    'source',
    'source_id',
    'name',
    'icon',
    'meal',
    'quantity',
    'units',
    'calories',
    'fat',
    'protein',
    'carbs',
    'saturated_fat',
    'sugars',
    'fibre',
    'cholesterol',
    'sodium',
    'timezone',
])]
class Calorie extends Model implements DefinesContentSchema, HasMedia, Timelineable
{
    /** @use HasFactory<CalorieFactory> */
    use HasAttachments, HasFactory, HasTimelineEntry;

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
        $table->string('source')->nullable();
        $table->string('source_id')->nullable();
        $table->string('name');
        $table->string('icon')->nullable();
        $table->string('meal');
        $table->decimal('quantity', 8, 2);
        $table->string('units');
        $table->integer('calories');
        $table->decimal('fat', 8, 2)->nullable();
        $table->decimal('protein', 8, 2)->nullable();
        $table->decimal('carbs', 8, 2)->nullable();
        $table->decimal('saturated_fat', 8, 2)->nullable();
        $table->decimal('sugars', 8, 2)->nullable();
        $table->decimal('fibre', 8, 2)->nullable();
        $table->decimal('cholesterol', 8, 2)->nullable();
        $table->decimal('sodium', 8, 2)->nullable();
        $table->timestamps();
    }

    protected static function booted(): void
    {
        static::saving(function (Calorie $calorie): void {
            if (blank($calorie->ulid)) {
                $calorie->ulid = (string) Str::ulid();
            }
        });

        static::saved(function (Calorie $calorie): void {
            app(CalorieDayFileSynchronizer::class)->sync($calorie->occurred_at);

            if ($calorie->wasChanged('occurred_at') && $calorie->getOriginal('occurred_at')) {
                app(CalorieDayFileSynchronizer::class)->sync($calorie->getOriginal('occurred_at'));
            }
        });

        static::deleted(function (Calorie $calorie): void {
            app(CalorieDayFileSynchronizer::class)->sync($calorie->occurred_at);
        });
    }

    public function slug(): string
    {
        return 'calories';
    }

    public function flatFileType(): string
    {
        return 'calorie';
    }

    public function card(): array
    {
        $dailyTotal = self::whereDate('occurred_at', $this->occurred_at->toDateString())
            ->sum('calories');

        return [
            'type' => 'calorie',
            'icon' => 'utensils',
            'title' => number_format($dailyTotal).' kcal',
            'titleLabel' => 'Food log, '.number_format($dailyTotal).' kcal for the day',
            'subtitle' => $this->cardSubtitle(),
            'occurred_at' => $this->occurred_at,
            'accent' => 'food',
            'meta' => [],
        ];
    }

    private function cardSubtitle(): ?string
    {
        $totals = self::whereDate('occurred_at', $this->occurred_at->toDateString())
            ->selectRaw('SUM(protein) as protein, SUM(carbs) as carbs, SUM(fat) as fat')
            ->first();

        $parts = [];

        if ($totals->protein) {
            $parts[] = round($totals->protein).'g protein';
        }

        if ($totals->carbs) {
            $parts[] = round($totals->carbs).'g carbs';
        }

        if ($totals->fat) {
            $parts[] = round($totals->fat).'g fat';
        }

        return $parts ? implode(', ', $parts) : null;
    }
}
