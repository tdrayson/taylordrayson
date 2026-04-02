<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('import:all {--fresh : Run migrate:fresh before importing}')]
#[Description('Import all CSV files from the data directory')]
class ImportAll extends Command
{
    /** @var list<array{file: string, type: string}> */
    private array $imports = [
        ['file' => 'data/airports.csv', 'type' => 'airport'],
        ['file' => 'data/airlines.csv', 'type' => 'airline'],
        ['file' => 'data/calories.csv', 'type' => 'calorie'],
        ['file' => 'data/flights.csv', 'type' => 'flight'],
        ['file' => 'data/fuel.csv', 'type' => 'fuel'],
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

        return self::SUCCESS;
    }
}
