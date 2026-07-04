<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\TimelineEntry;
use App\Support\LocalTime;
use App\Support\OgMeta;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntryController extends Controller
{
    public function show(int $year, int $month, int $day, string $slug): Response
    {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $entries = TimelineEntry::query()
            ->with('timelineable')
            ->whereDate('occurred_at', $date)
            ->get();

        $entry = $entries->first(function (TimelineEntry $entry) use ($slug) {
            return $entry->timelineable?->slug() === $slug;
        });

        if (! $entry?->timelineable) {
            throw new NotFoundHttpException;
        }

        $model = $entry->timelineable;

        if ($model instanceof Flight) {
            $model->load('airline', 'origin', 'destination');
        }

        if ($model instanceof Appearance || $model instanceof Activity) {
            $model->load('media');
        }

        $card = $model->card();

        return Inertia::render('Entry', [
            'type' => $card['type'],
            'accent' => $card['accent'],
            'title' => $card['title'],
            ...$this->occurredFields($entry->occurred_at, $model->timezone(), LocalTime::isDayLevel($card['type'])),
            'og' => OgMeta::entry($entry, $card['title']),
            'dayUrl' => sprintf('/%04d/%02d/%02d', $year, $month, $day),
            'entry' => $model instanceof Calorie
                ? $this->calorieDay($model)
                : $this->entryPayload($model),
            'polyline' => data_get($model, 'meta.polyline'),
            'source' => $this->source($model),
        ]);
    }

    /**
     * Local-time display fields for the entry header.
     *
     * @return array{occurredAt: string, occurredLabel: string, occurredOffset: string}
     */
    private function occurredFields(CarbonInterface $occurredAt, ?string $timezone, bool $dateOnly): array
    {
        $local = LocalTime::for($occurredAt, $timezone, $dateOnly);

        return [
            'occurredAt' => $local['iso'],
            'occurredLabel' => $local['label'],
            'occurredOffset' => $local['offset'],
        ];
    }

    /**
     * Serialise a timeline model for its detail page, dropping audit timestamps
     * and adding the resolved thumbnail URL for media appearances.
     *
     * @return array<string, mixed>
     */
    private function entryPayload(Model $model): array
    {
        $data = Arr::except($model->toArray(), ['created_at', 'updated_at']);

        if ($model instanceof Appearance) {
            $data['thumbnail'] = $model->thumbnailUrl();
            $data['thumbnailSrcset'] = $model->thumbnailSrcset();
        }

        if ($model instanceof Activity) {
            $data['photos'] = $model->galleryPhotos();
        }

        return $data;
    }

    /**
     * A food entry represents a whole day's eating, so aggregate every calorie
     * row for the date into day totals plus a per-meal breakdown.
     *
     * @return array{totals: array<string, float|int>, meals: array<int, array<string, mixed>>}
     */
    private function calorieDay(Calorie $model): array
    {
        $items = Calorie::query()
            ->whereDate('occurred_at', $model->occurred_at->toDateString())
            ->orderBy('occurred_at')
            ->get();

        $mealOrder = ['breakfast' => 0, 'lunch' => 1, 'dinner' => 2, 'snacks' => 3];

        return [
            // True while the day is still today, so the page can flag that more
            // food may yet be logged. Computed server-side to avoid client tz math.
            'inProgress' => $model->occurred_at->isToday(),
            'totals' => [
                'calories' => (int) $items->sum('calories'),
                'protein' => round((float) $items->sum('protein'), 1),
                'carbs' => round((float) $items->sum('carbs'), 1),
                'fat' => round((float) $items->sum('fat'), 1),
                'saturated_fat' => round((float) $items->sum('saturated_fat'), 1),
                'sugars' => round((float) $items->sum('sugars'), 1),
                'fibre' => round((float) $items->sum('fibre'), 1),
                'sodium' => (int) round((float) $items->sum('sodium')),
            ],
            'meals' => $items->groupBy('meal')
                ->map(fn ($group, string $meal): array => [
                    'meal' => $meal,
                    'calories' => (int) $group->sum('calories'),
                    'items' => $group->map(fn (Calorie $item): array => [
                        'name' => $item->name,
                        'calories' => (int) $item->calories,
                        'quantity' => (float) $item->quantity,
                        'units' => $item->units,
                    ])->values()->all(),
                ])
                ->sortBy(fn (array $meal): int => $mealOrder[$meal['meal']] ?? 99)
                ->values()
                ->all(),
        ];
    }

    /**
     * Where this entry's data came from, with a link back to the original when available.
     *
     * @return array{platform: string, url: ?string}|null
     */
    private function source(Model $model): ?array
    {
        $platform = $model->source ?? null;

        if (! $platform) {
            return null;
        }

        return [
            'platform' => $platform,
            'url' => $model->platform_url,
        ];
    }
}
