<?php

namespace App\Http\Controllers;

use App\Models\TimelineEntry;
use Illuminate\View\View;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class EntryController extends Controller
{
    public function show(int $year, int $month, int $day, string $slug): View
    {
        $date = sprintf('%04d-%02d-%02d', $year, $month, $day);

        $entries = TimelineEntry::query()
            ->with('timelineable')
            ->whereDate('occurred_at', $date)
            ->get();

        $entry = $entries->first(function (TimelineEntry $entry) use ($slug) {
            return $entry->timelineable?->slug() === $slug;
        });

        if (! $entry?->timelineable) {
            throw new NotFoundHttpException;
        }

        $card = $entry->timelineable->card();

        return view('pages.entry', [
            'entry' => $entry->timelineable,
            'card' => $card,
            'date' => $entry->occurred_at,
        ]);
    }
}
