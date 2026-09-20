<?php

namespace App\Jobs;

use App\Support\Health\HealthPayloadSummary;
use App\Support\Health\HealthProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Run one Health Auto Export send through the processor its endpoint chose.
 *
 * The route decides which processor applies, so there is no metric routing
 * here: a payload reaching this job has already been validated as belonging to
 * that processor.
 */
class ProcessHealthExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /**
     * @param  array<string, mixed>  $payload
     * @param  class-string<HealthProcessor>  $processor
     */
    public function __construct(public array $payload, public string $processor) {}

    public function handle(): void
    {
        // These sends are processed out of band, so the structural summary is the
        // only record of what actually arrived when a night comes out wrong.
        Log::info('health export received', HealthPayloadSummary::for($this->payload));

        app($this->processor)->process($this->payload);
    }
}
