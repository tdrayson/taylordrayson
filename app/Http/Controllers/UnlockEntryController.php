<?php

namespace App\Http\Controllers;

use App\Actions\Entries\UnlockEntry;
use App\Http\Requests\UnlockEntryRequest;
use App\Queries\EntryByDataset;
use Illuminate\Http\RedirectResponse;

final class UnlockEntryController extends Controller
{
    public function __invoke(UnlockEntryRequest $request, string $dataset, int $id, EntryByDataset $entries, UnlockEntry $unlock): RedirectResponse
    {
        if (! $unlock($entries($dataset, $id), $request->validated('password'), $request->session())) {
            return back()->withErrors(['password' => 'That password is not right.']);
        }

        return back();
    }
}
