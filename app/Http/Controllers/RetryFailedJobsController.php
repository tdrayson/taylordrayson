<?php

namespace App\Http\Controllers;

use App\Actions\Hub\RetryFailedJobs;
use Illuminate\Http\RedirectResponse;

/**
 * Pushes every failed job back onto the queue. All or nothing: the decision is
 * the same for all of them, which is why the hub shows them as one row.
 */
class RetryFailedJobsController extends Controller
{
    public function __invoke(RetryFailedJobs $retry): RedirectResponse
    {
        $retry();

        return back();
    }
}
