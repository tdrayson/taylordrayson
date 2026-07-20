<?php

namespace App\Http\Controllers;

use App\Models\LeaderboardEntry;
use App\Support\OgMeta;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Leaderboard', [
            'og' => OgMeta::leaderboard(),
            'entries' => LeaderboardEntry::topEntries(null),
        ]);
    }
}
