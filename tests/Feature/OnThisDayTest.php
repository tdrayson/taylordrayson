<?php

use App\Models\Activity;
use App\Models\Note;
use Illuminate\Support\Carbon;

use function Pest\Laravel\get;

beforeEach(function () {
    // Freeze "today" so the day-of-year query is deterministic regardless of when the suite runs.
    Carbon::setTestNow('2026-07-05 09:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
});

it('groups entries sharing today\'s month and day by year, newest first', function () {
    Note::factory()->create(['occurred_at' => '2022-07-05 08:00:00']);
    Activity::factory()->create(['occurred_at' => '2024-07-05 18:00:00']);
    Note::factory()->create(['occurred_at' => '2026-07-05 07:30:00']);
    // Decoys: same day-of-month wrong month, and same month wrong day.
    Note::factory()->create(['occurred_at' => '2023-08-05 10:00:00']);
    Note::factory()->create(['occurred_at' => '2021-07-04 10:00:00']);

    get('/on-this-day')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnThisDay')
            ->where('date', '5 July')
            ->where('entriesCount', 3)
            ->where('yearsCount', 3)
            ->has('groups', 3)
            ->where('groups.0.date', '2026-07-05')
            ->where('groups.0.href', '/2026/07/05')
            ->where('groups.2.date', '2022-07-05'));
});

it('shows an empty state when nothing else landed on today', function () {
    Note::factory()->create(['occurred_at' => '2025-01-01 10:00:00']);

    get('/on-this-day')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('OnThisDay')
            ->where('entriesCount', 0)
            ->where('yearsCount', 0)
            ->has('groups', 0));
});
