<?php

namespace App\Models;

use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\HasStatus;
use App\Models\Concerns\HasSubjects;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\FoodTimelineObserver;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;

#[ObservedBy(FoodTimelineObserver::class)]
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
    'status',
    'password',
])]
class Food extends Model implements HasMedia, Timelineable
{
    use HasAttachments, HasFactory, HasStatus, HasSubjects, HasTimelineEntry;

    protected $table = 'food';

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
        static::saving(function (self $food): void {
            if ($food->occurred_at !== null) {
                $food->occurred_at = $food->occurred_at->copy()->endOfDay();
            }
        });

        // A food day is one entry, so a newly synced row (no status of its
        // own) joins the status the day already has rather than the model
        // default, or a sync could republish a day the owner unlisted. The
        // day's existing status always wins, even over a status the new row
        // was explicitly given.
        static::creating(function (self $food): void {
            $sibling = $food->occurred_at === null
                ? null
                : static::whereDate('occurred_at', $food->occurred_at->toDateString())->first();

            if ($sibling !== null) {
                $food->status = $sibling->status;
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
        return 'food';
    }
}
