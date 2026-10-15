<?php

namespace App\Console\Commands;

use App\Models\Scopes\ListedScope;
use App\Models\Sleep;
use App\Support\Health\SleepAggregator;
use App\Support\Health\SleepProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sleep:flatten-overlaps {--dry-run : List the nights that would change without saving}')]
#[Description('Re-derive stored nights whose stages hold duplicate Oura copies, then rescore')]
class FlattenSleepOverlaps extends Command
{
    /**
     * Rebuild each night whose stored stages overlap, keeping its stored window:
     * stages carry no "In Bed" envelope, so rebuilt bounds can fall short of it.
     */
    public function handle(SleepAggregator $aggregator, SleepProcessor $processor): int
    {
        $rows = [];

        Sleep::query()->withoutGlobalScope(ListedScope::class)->whereNotNull('stages')->orderBy('occurred_at')
            ->each(function (Sleep $sleep) use ($aggregator, &$rows): void {
                if (! $this->overlaps($sleep->stages)) {
                    return;
                }

                $record = $aggregator->resplitRow($sleep->occurred_at->toDateString(), (string) $sleep->source, $sleep->stages);
                $record['duration'] = max(0, (int) $sleep->started_at->diffInSeconds($sleep->occurred_at) - $record['awake']);

                $rows[] = [
                    $sleep->occurred_at->toDateString(),
                    $this->hours((int) $sleep->duration).' -> '.$this->hours($record['duration']),
                    $this->hours((int) $sleep->awake).' -> '.$this->hours($record['awake']),
                ];

                if (! $this->option('dry-run')) {
                    $sleep->forceFill(array_intersect_key($record, array_flip(['duration', 'awake', 'rem', 'core', 'deep', 'stages'])))->save();
                }
            });

        $this->table(['Night', 'Asleep', 'Awake'], $rows);

        if ($this->option('dry-run')) {
            $this->components->info(count($rows).' nights would change.');

            return self::SUCCESS;
        }

        $processor->scoreAll();
        $this->components->info(count($rows).' nights rebuilt and every night rescored.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, array{stage: string, start: string, end: string}>  $stages
     */
    private function overlaps(array $stages): bool
    {
        usort($stages, fn (array $first, array $second): int => strcmp($first['start'], $second['start']));
        $covered = null;

        foreach ($stages as $stage) {
            if ($covered !== null && $stage['start'] < $covered) {
                return true;
            }

            $covered = max($covered ?? $stage['end'], $stage['end']);
        }

        return false;
    }

    private function hours(int $seconds): string
    {
        return sprintf('%dh%02d', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
    }
}
