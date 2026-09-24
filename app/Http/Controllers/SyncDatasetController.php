<?php

namespace App\Http\Controllers;

use App\Datasets\Datasets;
use App\Jobs\RunSyncCommand;
use Illuminate\Http\RedirectResponse;

/**
 * Pulls one synced type now, for the edits a service never pushes.
 */
class SyncDatasetController extends Controller
{
    public function __invoke(string $dataset): RedirectResponse
    {
        $command = Datasets::for($dataset)?->syncCommand();

        abort_if($command === null, 404);

        RunSyncCommand::dispatch($command);

        return back();
    }
}
