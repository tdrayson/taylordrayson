<?php

namespace App\Console\Commands\Health;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('health:inspect {--list : List every captured payload instead of summarising the latest} {--ref= : Inspect a specific capture reference}')]
#[Description('Summarise captured Health Auto Export payloads (metrics, units, point counts, workout fields) without opening the raw files')]
class InspectHealthExport extends Command
{
    private const DIRECTORY = 'health/incoming';

    public function handle(): int
    {
        $disk = Storage::disk('local');

        $references = collect($disk->files(self::DIRECTORY))
            ->filter(fn (string $path): bool => str_ends_with($path, '.summary.json'))
            ->map(fn (string $path): string => str_replace('.summary.json', '', basename($path)))
            ->sort()
            ->values();

        if ($references->isEmpty()) {
            $this->components->warn('No captures yet. Point Health Auto Export at the ingest endpoint and send a payload.');

            return self::SUCCESS;
        }

        if ($this->option('list')) {
            $this->components->twoColumnDetail('<fg=gray>Reference</>', '<fg=gray>Metrics / Workouts</>');

            foreach ($references as $reference) {
                $summary = $this->summary($reference);
                $this->components->twoColumnDetail($reference, ($summary['metric_count'] ?? 0).' / '.($summary['workout_count'] ?? 0));
            }

            return self::SUCCESS;
        }

        $reference = $this->option('ref') ?: $references->last();
        $summary = $this->summary($reference);

        if ($summary === null) {
            $this->components->error("No capture found for reference: {$reference}");

            return self::FAILURE;
        }

        $this->components->info("Capture {$reference}");
        $this->line('  Top-level keys: '.implode(', ', $summary['top_level_keys'] ?? []));
        $this->newLine();

        foreach ($summary['metrics'] ?? [] as $metric) {
            $this->components->twoColumnDetail(
                "<fg=cyan>{$metric['name']}</> <fg=gray>({$metric['points']} pts".($metric['units'] ? ', '.$metric['units'] : '').')</>',
                implode(', ', $metric['fields'] ?? [])
            );
        }

        foreach ($summary['workouts'] ?? [] as $workout) {
            $this->components->twoColumnDetail("<fg=yellow>workout: {$workout['name']}</>", implode(', ', $workout['fields'] ?? []));
        }

        return self::SUCCESS;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function summary(string $reference): ?array
    {
        $path = self::DIRECTORY."/{$reference}.summary.json";
        $disk = Storage::disk('local');

        if (! $disk->exists($path)) {
            return null;
        }

        $decoded = json_decode((string) $disk->get($path), true);

        return is_array($decoded) ? $decoded : null;
    }
}
