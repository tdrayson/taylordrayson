<?php

namespace App\Models;

use App\Models\Concerns\HasAssets;
use App\Models\Concerns\HasTimelineEntry;
use App\Models\Concerns\Timelineable;
use App\Observers\CalorieTimelineObserver;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy(CalorieTimelineObserver::class)]
#[Fillable([
    'occurred_at',
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
class Calorie extends Model implements Timelineable
{
    use HasAssets, HasFactory, HasTimelineEntry;

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
     * @return array{type: string, icon: string, title: string, subtitle: ?string, occurred_at: Carbon, accent: string, meta: array}
     */
    public function toTimelineCard(): array
    {
        $dailyTotal = self::whereDate('occurred_at', $this->occurred_at->toDateString())
            ->sum('calories');

        return [
            'type' => 'calorie',
            'icon' => 'utensils',
            'title' => number_format($dailyTotal).' kcal',
            'subtitle' => null,
            'occurred_at' => $this->occurred_at,
            'accent' => 'food',
            'meta' => [],
        ];
    }
}
