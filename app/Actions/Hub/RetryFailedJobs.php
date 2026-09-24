<?php

namespace App\Actions\Hub;

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Queue\Failed\FailedJobProviderInterface;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Pushes every failed job back onto the queue, one at a time, forgetting any
 * whose model has since been deleted: that job can never run again.
 */
final class RetryFailedJobs
{
    public function __construct(private FailedJobProviderInterface $failer) {}

    public function __invoke(): void
    {
        foreach ($this->failer->ids() as $id) {
            try {
                $exitCode = Artisan::call('queue:retry', ['id' => [$id]]);
            } catch (ModelNotFoundException) {
                $this->failer->forget($id);

                continue;
            }

            if ($exitCode !== 0) {
                throw new RuntimeException(trim(Artisan::output()));
            }
        }
    }
}
