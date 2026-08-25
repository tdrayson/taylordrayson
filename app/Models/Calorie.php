<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\CalorieTimelineObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(CalorieTimelineObserver::class)]
#[Fillable([
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
])]
class Calorie extends Model implements HasMedia, Timelineable
{
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

    /**
     * Snap every row to the end of the day it belongs to.
     *
     * Rovi supplies a date and a meal label and no clock, so any time on the
     * way in is invented. Normalising here keeps the row and its spine entry on
     * the same moment, which is what stopped food displaying one time while
     * sorting by another.
     */
    protected static function booted(): void
    {
        static::saving(function (self $calorie): void {
            if ($calorie->occurred_at !== null) {
                $calorie->occurred_at = $calorie->occurred_at->copy()->endOfDay();
            }
        });
    }

    /**
     * A day's food is a total, not a moment: Rovi gives a date and a meal
     * label and no clock, so every row is stored at the end of its day.
     */
    public function hasClockTime(): bool
    {
        return false;
    }

    public function slug(): string
    {
        return 'calories';
    }
}
