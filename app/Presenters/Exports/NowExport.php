<?php

namespace App\Presenters\Exports;

use App\Data\ExportData;
use App\Data\ExportField;
use App\Data\ExportInstant;
use App\Data\ExportLink;
use App\Presenters\Exports\Sheets\NowSheet;
use App\Queries\CurrentlyReading;
use App\Queries\LastNightSleep;
use App\Queries\LatestEpisode;
use App\Queries\NowState;
use App\Support\StateStore;
use App\Support\Units;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;

/**
 * The /now dashboard as an export: the same readings the page shows, shaped
 * as labelled fields. No aspects: nothing here is a moment or a place.
 *
 * The ambient groups (where, weather, battery, rings) are the phone's last
 * report and may each be missing independently, so every one of them is
 * built through maybe() rather than assumed present.
 */
final class NowExport
{
    public function sheet(): NowSheet
    {
        return new NowSheet;
    }

    public function present(): ExportData
    {
        $sleep = app(LastNightSleep::class)();
        $book = app(CurrentlyReading::class)->book();
        $episode = app(LatestEpisode::class)();
        $ambient = (new NowState(new StateStore))();

        return new ExportData(
            type: 'now',
            url: url('/now'),
            title: 'Now',
            summary: null,
            // A snapshot is worthless without the moment it was taken. The
            // night itself needs no date: LastNightSleep only ever returns
            // last night, and the board is called Now.
            occurred: $this->observed($ambient),
            fields: array_values(array_filter([
                ...$this->where($ambient['location'] ?? null),
                ...$this->weather($ambient['weather'] ?? null),
                ...$this->rings($ambient['rings'] ?? null),
                ...$this->battery($ambient['battery'] ?? null),
                $sleep === null ? null : ExportField::make('slept', 'Slept', Units::humanDuration($sleep->duration), $sleep->duration),
                $book === null ? null : ExportField::make('reading', 'Book', $book->title, $book->title),
                $book?->meta->author === null ? null : ExportField::make('reading_author', 'Author', (string) $book->meta->author, (string) $book->meta->author),
                $episode === null ? null : ExportField::make('season', 'Season', (string) $episode->season_number, $episode->season_number),
                $episode === null ? null : ExportField::make('episode', 'Episode', (string) $episode->episode_number, $episode->episode_number),
                $episode?->duration === null ? null : ExportField::make('episode_duration', 'Duration', Units::preciseDuration($episode->duration), $episode->duration),
            ])),
            links: array_values(array_filter([
                $sleep === null ? null : ExportLink::make('sleep', 'Sleep', 'Sleep', '/sleep'),
                $book === null ? null : ExportLink::make('reading', 'Reading', $book->title, $book->url()),
                $episode === null ? null : ExportLink::make('episode', 'Episode', $episode->title, $episode->url()),
            ])),
        );
    }

    /**
     * The most recent reading across the ambient groups, in the timezone the
     * phone last reported from. Null when the phone has never reported.
     *
     * @param  array<string, array<string, mixed>|null>  $ambient
     */
    private function observed(array $ambient): ?ExportInstant
    {
        $stamps = array_filter(array_map(
            fn (?array $group): ?string => $group['observedAt'] ?? null,
            $ambient,
        ));

        if ($stamps === []) {
            return null;
        }

        $zone = $ambient['location']['timezone'] ?? config('app.timezone');

        return ExportInstant::for(CarbonImmutable::parse(max($stamps))->setTimezone($zone), $zone);
    }

    /**
     * @param  array<string, mixed>|null  $location
     * @return list<ExportField|null>
     */
    private function where(?array $location): array
    {
        if ($location === null) {
            return [];
        }

        $place = implode(', ', array_filter([$location['city'] ?? null, $location['state'] ?? null]));
        $zone = $location['timezone'] ?? null;
        $abbr = $location['tzAbbr'] ?? null;

        return [
            ExportField::maybe('location', 'Location', $place === '' ? null : $place, $place),
            ExportField::maybe('country', 'Country', $location['country'] ?? null, $location['countryCode'] ?? null),
            ExportField::maybe('timezone', 'Time zone', $zone === null ? null : $zone.($abbr === null ? '' : " ({$abbr})"), $zone),
        ];
    }

    /**
     * @param  array<string, mixed>|null  $weather
     * @return list<ExportField|null>
     */
    private function weather(?array $weather): array
    {
        if ($weather === null) {
            return [];
        }

        return [
            ExportField::maybe('conditions', 'Conditions', isset($weather['condition']) ? Str::headline((string) $weather['condition']) : null, $weather['condition'] ?? null),
            ExportField::maybe('temperature', 'Temperature', isset($weather['temp']) ? "{$weather['temp']}°C" : null, $weather['temp'] ?? null),
            ExportField::maybe('humidity', 'Humidity', isset($weather['humidity']) ? "{$weather['humidity']}%" : null, $weather['humidity'] ?? null),
            ExportField::maybe('wind', 'Wind', isset($weather['wind']) ? "{$weather['wind']} mph" : null, $weather['wind'] ?? null),
        ];
    }

    /**
     * Each ring against the goal it is measured by, which is the only way a
     * bare "88" means anything.
     *
     * @param  array<string, mixed>|null  $rings
     * @return list<ExportField|null>
     */
    private function rings(?array $rings): array
    {
        if ($rings === null) {
            return [];
        }

        return [
            $this->ring($rings, 'move', 'Move', 'move', 'moveGoal', 'kcal'),
            $this->ring($rings, 'exercise', 'Exercise', 'exercise', 'exerciseGoal', 'min'),
            $this->ring($rings, 'stand', 'Stand', 'stand', 'standGoal', 'hrs'),
            ExportField::maybe('steps', 'Steps', isset($rings['steps']) ? number_format((int) $rings['steps']) : null, $rings['steps'] ?? null),
        ];
    }

    /** @param  array<string, mixed>  $rings */
    private function ring(array $rings, string $key, string $label, string $value, string $goal, string $unit): ?ExportField
    {
        if (! isset($rings[$value], $rings[$goal])) {
            return null;
        }

        return ExportField::make($key, $label, "{$rings[$value]} of {$rings[$goal]} {$unit}", [
            'value' => $rings[$value],
            'goal' => $rings[$goal],
        ]);
    }

    /**
     * @param  array<string, mixed>|null  $battery
     * @return list<ExportField|null>
     */
    private function battery(?array $battery): array
    {
        if ($battery === null || ! isset($battery['percent'])) {
            return [];
        }

        return [
            ExportField::make('battery', 'Battery', "{$battery['percent']}%", $battery['percent']),
            ExportField::maybe('device', 'Model', $battery['device'] ?? null, $battery['device'] ?? null),
            ExportField::make('charging', 'Charging', ($battery['charging'] ?? false) ? 'Yes' : 'No', (bool) ($battery['charging'] ?? false)),
        ];
    }
}
