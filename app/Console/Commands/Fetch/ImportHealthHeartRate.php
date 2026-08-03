<?php

namespace App\Console\Commands\Fetch;

use App\Support\Health\HeartRateProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('health:heart_rate {--file= : Process a specific raw JSON payload path} {--csv= : Target CSV to mirror into (defaults to data/activities.csv)} {--max-points=240 : Cap the stored per-activity series, downsampling evenly when exceeded} {--overwrite : Recompute the average and max even where a source already set them}')]
#[Description('Attach heart-rate to activities from captured Health Auto Export heart_rate data, windowing samples into each activity and merging across batched payloads (database and data/activities.csv)')]
class ImportHealthHeartRate extends Command
{
    public function handle(HeartRateProcessor $processor): int
    {
        $file = $this->option('file');

        if (! is_string($file) || $file === '') {
            $this->components->info('Heart-rate now ingests from POST /api/v1/health-export. Use --file to reprocess a payload.');

            return self::SUCCESS;
        }

        $payload = json_decode((string) file_get_contents($file), true);

        if (! is_array($payload)) {
            $this->components->error("Not a JSON payload: {$file}");

            return self::FAILURE;
        }

        $processor->process(
            $payload,
            $this->option('csv') ?: null,
            (bool) $this->option('overwrite'),
            max(0, (int) $this->option('max-points')),
        );

        $this->components->info('Processed heart-rate payload.');

        return self::SUCCESS;
    }
}
