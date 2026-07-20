<?php

namespace App\Jobs;

use App\Support\Health\HealthProcessor;
use App\Support\Health\HeartRateProcessor;
use App\Support\Health\SleepProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class ProcessHealthExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload) {}

    public function handle(): void
    {
        /** @var list<HealthProcessor> $processors */
        $processors = [app(SleepProcessor::class), app(HeartRateProcessor::class)];

        $metricNames = array_values(array_filter(array_map(
            fn ($metric): ?string => is_array($metric) ? ($metric['name'] ?? null) : null,
            $this->payload['data']['metrics'] ?? [],
        )));

        $claimed = [];
        $failures = [];

        foreach ($processors as $processor) {
            $matched = array_intersect($processor->handles(), $metricNames);

            if ($matched === []) {
                continue;
            }

            $claimed = array_merge($claimed, $matched);

            try {
                $processor->process($this->payload);
            } catch (Throwable $exception) {
                report($exception);
                $failures[] = $processor::class;
            }
        }

        $unhandled = array_values(array_diff($metricNames, $claimed));

        if ($unhandled !== []) {
            Log::info('health.ingest unhandled metrics', ['metrics' => $unhandled]);
        }

        if ($failures !== []) {
            throw new RuntimeException('Health processors failed: '.implode(', ', $failures));
        }
    }
}
