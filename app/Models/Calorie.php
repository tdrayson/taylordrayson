<?php

namespace App\Models;

use App\Data\CardData;
use App\Data\CardMeta;
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

    public function slug(): string
    {
        return 'calories';
    }

    public function card(): CardData
    {
        $dailyTotal = self::whereDate('occurred_at', $this->occurred_at->toDateString())
            ->sum('calories');

        return new CardData(
            type: 'calorie',
            icon: 'utensils',
            title: number_format($dailyTotal).' kcal',
            titleLabel: 'Food log, '.number_format($dailyTotal).' kcal for the day',
            subtitle: $this->cardSubtitle(),
            subtitleTokens: null,
            occurredAt: $this->occurred_at,
            accent: 'food',
            range: null,
            meta: CardMeta::empty(),
        );
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
