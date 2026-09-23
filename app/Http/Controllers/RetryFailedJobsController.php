<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;
use RuntimeException;

/**
 * Pushes every failed job back onto the queue. All or nothing: the decision is
 * the same for all of them, which is why the hub shows them as one row.
 */
class RetryFailedJobsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $exitCode = Artisan::call('queue:retry', ['id' => ['all']]);

        if ($exitCode !== 0) {
            throw new RuntimeException(trim(Artisan::output()));
        }

        return back();
    }
}
