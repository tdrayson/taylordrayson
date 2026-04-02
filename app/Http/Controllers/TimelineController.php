<?php

namespace App\Http\Controllers;

use App\Models\TimelineEntry;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function index(): View
    {
        $entries = TimelineEntry::query()
            ->with('timelineable')
            ->orderByDesc('occurred_at')
            ->paginate(50);

        return view('pages.home', [
            'entries' => $entries,
        ]);
    }
}
