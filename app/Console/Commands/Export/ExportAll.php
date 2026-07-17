<?php

namespace App\Console\Commands\Export;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('export:all')]
#[Description('Export all maintained database tables back to their data CSV files (the reverse of import:all)')]
class ExportAll extends Command
{
    /** @var list<array{file: string, type: string}> */
    private array $exports = [
        ['file' => 'data/calories.csv', 'type' => 'calorie'],
        ['file' => 'data/appearances.csv', 'type' => 'appearance'],
        ['file' => 'data/sleep.csv', 'type' => 'sleep'],
        ['file' => 'data/activities.csv', 'type' => 'activity'],
        ['file' => 'data/podcasts.csv', 'type' => 'podcast'],
        ['file' => 'data/flights.csv', 'type' => 'flight'],
        ['file' => 'data/fuel.csv', 'type' => 'fuel'],
    ];

    public function handle(): int
    {
        foreach ($this->exports as $export) {
            $this->call('export:csv', [
                'file' => base_path($export['file']),
                'type' => $export['type'],
            ]);
        }

        return self::SUCCESS;
    }
}
