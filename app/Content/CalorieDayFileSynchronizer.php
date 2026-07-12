<?php

namespace App\Content;

use App\Models\Calorie;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Yaml\Yaml;

class CalorieDayFileSynchronizer
{
    public function sync(CarbonInterface|string $date): void
    {
        $day = Carbon::parse($date)->startOfDay();
        $items = Calorie::query()
            ->whereDate('occurred_at', $day->toDateString())
            ->orderBy('id')
            ->get();

        $absolute = rtrim((string) config('content.path'), DIRECTORY_SEPARATOR)
            .DIRECTORY_SEPARATOR
            .str_replace('/', DIRECTORY_SEPARATOR, $day->format('Y/m/d').'/calories.md');

        if ($items->isEmpty()) {
            if (File::exists($absolute)) {
                File::delete($absolute);
            }

            return;
        }

        foreach ($items as $item) {
            if (blank($item->ulid)) {
                $item->ulid = (string) Str::ulid();
                $item->saveQuietly();
            }
        }

        $dayId = $this->dayId($absolute);
        $timezone = $items->first()->getAttributes()['timezone'] ?? null;

        $document = array_filter([
            'id' => $dayId,
            'type' => 'calorie',
            'occurred_at' => $day->copy()->setTime(12, 0)->toIso8601String(),
            'timezone' => $timezone,
            'slug' => 'calories',
            'items' => $items->map(fn (Calorie $calorie): array => array_filter([
                'id' => $calorie->ulid,
                'occurred_at' => $calorie->occurred_at?->toIso8601String(),
                'source' => $calorie->source,
                'source_id' => $calorie->source_id,
                'name' => $calorie->name,
                'icon' => $calorie->icon,
                'meal' => $calorie->meal,
                'quantity' => $calorie->quantity,
                'units' => $calorie->units,
                'calories' => $calorie->calories,
                'fat' => $calorie->fat,
                'protein' => $calorie->protein,
                'carbs' => $calorie->carbs,
                'saturated_fat' => $calorie->saturated_fat,
                'sugars' => $calorie->sugars,
                'fibre' => $calorie->fibre,
                'cholesterol' => $calorie->cholesterol,
                'sodium' => $calorie->sodium,
            ], fn (mixed $value): bool => $value !== null && $value !== ''))->values()->all(),
        ], fn (mixed $value): bool => $value !== null && $value !== '');

        $yaml = Yaml::dump($document, 6, 2, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);

        File::ensureDirectoryExists(dirname($absolute));
        File::put($absolute, '---'."\n".$yaml.'---'."\n");
    }

    private function dayId(string $absolutePath): string
    {
        if (! File::exists($absolutePath)) {
            return (string) Str::ulid();
        }

        $parsed = app(EntryFileRepository::class)->parse(File::get($absolutePath));

        return (string) ($parsed['frontmatter']['id'] ?? Str::ulid());
    }
}
