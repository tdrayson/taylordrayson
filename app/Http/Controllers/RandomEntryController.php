<?php

namespace App\Http\Controllers;

use App\Models\TimelineEntry;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Redirect to a random timeline entry page — used by /lucky and /random.
 */
class RandomEntryController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        $entry = TimelineEntry::query()
            ->with('entry')
            ->whereNotNull('url_slug')
            ->whereHas('entry')
            ->inRandomOrder()
            ->first();

        if ($entry === null || $entry->entry === null) {
            throw new NotFoundHttpException;
        }

        return redirect()->to($entry->entry->url());
    }
}
