<?php

namespace App\Console\Commands;

use App\Content\ContentIndex;
use App\Content\EntryFileImporter;
use App\Models\Activity;
use App\Models\Airline;
use App\Models\Calorie;
use App\Models\Flight;
use App\Models\TimelineEntry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('content:bench {--json : Emit timings as JSON}')]
#[Description('Rebuild content.sqlite and time representative index queries')]
class ContentBenchCommand extends Command
{
    public function handle(ContentIndex $index, EntryFileImporter $importer): int
    {
        /** @var list<array<string, mixed>> $timings */
        $timings = [];

        $started = hrtime(true);
        $index->clear();
        $timings[] = ['step' => 'clear', 'ms' => $this->elapsedMs($started)];

        $started = hrtime(true);
        $index->migrate();
        $timings[] = ['step' => 'migrate', 'ms' => $this->elapsedMs($started)];

        $started = hrtime(true);
        $warmed = $index->using(fn (): array => $importer->importAll());
        $warmMs = $this->elapsedMs($started);
        $timings[] = [
            'step' => 'warm',
            'ms' => $warmMs,
            'rows' => count($warmed),
            'rows_per_sec' => $warmMs > 0 ? round(count($warmed) / ($warmMs / 1000), 1) : null,
        ];

        $index->using(function () use (&$timings): void {
            $timings[] = $this->timeQuery(
                'timeline_latest_50',
                fn (): int => TimelineEntry::query()->orderByDesc('occurred_at')->limit(50)->get()->count(),
            );

            $timings[] = $this->timeQuery(
                'timeline_day',
                fn (): int => TimelineEntry::query()->coveringDate('2026-01-09')->count(),
            );

            $timings[] = $this->timeQuery(
                'timeline_activities',
                fn (): int => TimelineEntry::query()
                    ->where('timelineable_type', Activity::class)
                    ->count(),
            );

            $timings[] = $this->timeQuery(
                'activity_count',
                fn (): int => Activity::query()->count(),
            );

            $timings[] = $this->timeQuery(
                'calorie_day_sum',
                fn (): int => (int) Calorie::query()
                    ->whereDate('occurred_at', '2026-01-09')
                    ->sum('calories'),
            );

            $timings[] = $this->timeQuery(
                'flights_with_airline',
                function (): int {
                    $flights = Flight::query()->orderByDesc('occurred_at')->limit(25)->get();
                    $icaos = $flights->pluck('airline_icao')->filter()->unique()->values();
                    $airlines = Airline::query()->whereIn('icao_code', $icaos)->get()->keyBy('icao_code');

                    return $flights
                        ->filter(fn (Flight $flight): bool => filled($flight->airline_icao) && $airlines->has($flight->airline_icao))
                        ->count();
                },
            );

            $timings[] = [
                'step' => 'index_counts',
                'ms' => 0,
                'timeline_entries' => TimelineEntry::query()->count(),
                'activities' => Activity::query()->count(),
                'calories' => Calorie::query()->count(),
                'flights' => Flight::query()->count(),
                'sqlite_page_count' => (int) (DB::selectOne('pragma page_count')->page_count ?? 0),
            ];
        });

        if ($this->option('json')) {
            $this->line(json_encode([
                'database' => $index->databasePath(),
                'content_path' => config('content.path'),
                'timings' => $timings,
            ], JSON_PRETTY_PRINT));

            return self::SUCCESS;
        }

        $this->info('Content index: '.$index->databasePath());
        $this->table(
            ['Step', 'ms', 'Detail'],
            collect($timings)->map(function (array $row): array {
                $detail = collect($row)
                    ->except(['step', 'ms'])
                    ->map(fn (mixed $value, string $key): string => "{$key}={$value}")
                    ->implode(' ');

                return [(string) $row['step'], $row['ms'], $detail];
            })->all(),
        );

        return self::SUCCESS;
    }

    /**
     * @param  callable(): int  $callback
     * @return array{step: string, ms: float, rows: int}
     */
    private function timeQuery(string $step, callable $callback): array
    {
        $started = hrtime(true);
        $rows = $callback();

        return ['step' => $step, 'ms' => $this->elapsedMs($started), 'rows' => $rows];
    }

    private function elapsedMs(int $started): float
    {
        return round((hrtime(true) - $started) / 1_000_000, 1);
    }
}
