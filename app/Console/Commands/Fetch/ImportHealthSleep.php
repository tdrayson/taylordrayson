<?php

namespace App\Console\Commands\Fetch;

use App\Support\Health\SleepProcessor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('health:sleep {--file= : Process a specific raw JSON payload path} {--score : Recompute the Apple-style sleep score for every night}')]
#[Description('Process a captured Health Auto Export sleep_analysis payload, or rescore the existing nights')]
class ImportHealthSleep extends Command
{
    public function handle(SleepProcessor $processor): int
    {
        if ($this->option('score')) {
            $this->components->info(sprintf('Scored %d nights.', $processor->scoreAll()));

            return self::SUCCESS;
        }

        $file = $this->option('file');

        if (! is_string($file) || $file === '') {
            $this->components->info('Sleep now ingests from POST /api/v1/health-export. Use --file to reprocess a payload, or --score to rescore.');

            return self::SUCCESS;
        }

        $payload = json_decode((string) file_get_contents($file), true);

        if (! is_array($payload)) {
            $this->components->error("Not a JSON payload: {$file}");

            return self::FAILURE;
        }

        $processor->process($payload);
        $this->components->info('Processed sleep payload.');

        return self::SUCCESS;
    }
}
