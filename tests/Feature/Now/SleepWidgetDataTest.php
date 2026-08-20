<?php

use App\Models\Sleep;
use Illuminate\Support\Carbon;

function nightOf(string $date, int $hours = 8): Sleep
{
    return Sleep::factory()->create([
        'occurred_at' => Carbon::parse($date)->startOfDay(),
        'duration' => $hours * 3600,
    ]);
}

function sleepProps(): array
{
    return test()->get('/now')->viewData('page')['props']['sleep'];
}

it('dates every night from its own record, not by counting back from today', function () {
    Carbon::setTestNow('2026-08-20 09:00:00');

    // A gap: nothing for the 19th or the 20th.
    nightOf('2026-08-18', 7);

    $nights = collect(sleepProps()['nights']);

    expect($nights)->toHaveCount(7)
        ->and($nights->last()['date'])->toBe('2026-08-20')
        ->and($nights->last()['hours'])->toBeNull()
        ->and($nights->firstWhere('date', '2026-08-18')['hours'])->toBe(7.0);
});

it('shows the night just gone', function () {
    Carbon::setTestNow('2026-08-20 09:00:00');
    nightOf('2026-08-20', 8);
    nightOf('2026-08-19', 6);

    expect(sleepProps()['lastNight']['date'])->toBe('2026-08-20');
});

it('falls back to the night before when the one just gone has no record', function () {
    Carbon::setTestNow('2026-08-20 09:00:00');
    nightOf('2026-08-19', 6);

    expect(sleepProps()['lastNight']['date'])->toBe('2026-08-19');
});

it('reports no last night rather than reaching further back', function () {
    Carbon::setTestNow('2026-08-20 09:00:00');

    // Two nights back is too old to call last night; the widget shows N/A.
    nightOf('2026-08-18', 7);

    expect(sleepProps()['lastNight'])->toBeNull();
});
