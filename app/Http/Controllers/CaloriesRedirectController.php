<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\TimelineEntry;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CaloriesRedirectController extends Controller
{
    /**
     * Old food day links used the `calories` slug; redirect to that date's food entry.
     */
    public function __invoke(int $year, int $month, int $day): RedirectResponse
    {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $entry = TimelineEntry::query()
            ->with('entry')
            ->where('dataset', (new Food)->getMorphClass())
            ->whereDate('occurred_at', $date)
            ->first();

        if ($entry?->entry === null) {
            throw new NotFoundHttpException;
        }

        $entry->entry->setRelation('timelineEntry', $entry);

        return redirect($entry->entry->url(), 301);
    }
}
