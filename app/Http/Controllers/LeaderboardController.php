<?php

namespace App\Http\Controllers;

use App\Models\LeaderboardEntry;
use App\Presenters\Heads\SiteHeads;
use App\Support\Head;
use Inertia\Inertia;
use Inertia\Response;

class LeaderboardController extends Controller
{
    public function __invoke(): Response
    {
        app(Head::class)->set(SiteHeads::leaderboard());

        return Inertia::render('Leaderboard', [
            'entries' => LeaderboardEntry::topEntries(null),
        ]);
    }
}
