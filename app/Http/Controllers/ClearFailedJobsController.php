<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Queue\Failed\FailedJobProviderInterface;

/**
 * Forgets every failed job without running it again.
 */
class ClearFailedJobsController extends Controller
{
    public function __invoke(FailedJobProviderInterface $failer): RedirectResponse
    {
        $failer->flush();

        return back();
    }
}
