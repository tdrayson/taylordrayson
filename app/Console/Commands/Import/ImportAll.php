<?php

namespace App\Console\Commands\Import;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:all {--fresh : Run migrate:fresh before importing}')]
#[Description('Import all CSV files from the data directory')]
class ImportAll extends Command
{
    /** @var list<array{file: string, type: string}> */
    private array $imports = [
        ['file' => 'data/calories.csv', 'type' => 'calorie'],
        ['file' => 'data/appearances.csv', 'type' => 'appearance'],
        ['file' => 'data/sleep.csv', 'type' => 'sleep'],
        ['file' => 'data/activities.csv', 'type' => 'activity'],
        ['file' => 'data/podcasts.csv', 'type' => 'podcast'],
        ['file' => 'data/flights.csv', 'type' => 'flight'],
        ['file' => 'data/fuel.csv', 'type' => 'fuel'],
        ['file' => 'data/events.csv', 'type' => 'event'],
    ];

    public function handle(): int
    {
        if ($this->option('fresh')) {
            $this->call('migrate:fresh', ['--no-interaction' => true]);
            $this->newLine();
        }

        foreach ($this->imports as $import) {
            if (! file_exists(base_path($import['file']))) {
                $this->warn("Skipping {$import['file']} (not found)");

                continue;
            }

            $this->call('import:csv', [
                'file' => base_path($import['file']),
                'type' => $import['type'],
            ]);
        }

        // Events carry their category as a shared tag, but the replace-on-import
        // drops those pivots, so re-seed them from the type column here rather
        // than relying on the operator to remember a follow-up command.
        if (file_exists(base_path('data/events.csv'))) {
            $this->call('events:tag-from-type');
        }

        return self::SUCCESS;
    }
}
