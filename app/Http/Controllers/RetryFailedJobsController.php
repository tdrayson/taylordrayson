<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Artisan;

/**
 * Pushes every failed job back onto the queue. All or nothing: the decision is
 * the same for all of them, which is why the hub shows them as one row.
 */
class RetryFailedJobsController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        Artisan::call('queue:retry', ['id' => ['all']]);

        return back();
    }
}
