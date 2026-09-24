<?php

namespace App\Http\Controllers;

use App\Actions\Entries\UpdateEntryStatus;
use App\Enums\EntryStatus;
use App\Http\Requests\UpdateEntryStatusRequest;
use App\Queries\EntryByDataset;
use Illuminate\Http\RedirectResponse;

final class UpdateEntryStatusController extends Controller
{
    public function __invoke(UpdateEntryStatusRequest $request, string $dataset, int $id, EntryByDataset $entries, UpdateEntryStatus $update): RedirectResponse
    {
        $model = $update($entries($dataset, $id), $request->enum('status', EntryStatus::class), $request->validated('password'));

        return redirect($model->url());
    }
}
