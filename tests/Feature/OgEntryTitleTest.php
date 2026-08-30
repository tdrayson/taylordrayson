<?php

use App\Actions\Og\BuildEntryOgData;
use App\Models\Calorie;
use App\Models\Media;
use App\Models\Series;
use App\Models\Sleep;
use App\Models\TimelineEntry;

/** The headline the OG card would print for the entry behind this model. */
function ogTitle(object $model): string
{
    $entry = TimelineEntry::query()
        ->where('timelineable_type', $model::class)
        ->where('timelineable_id', $model->id)
        ->with('timelineable')
        ->sole();

    return app(BuildEntryOgData::class)($entry, fn (): ?string => null)['title'];
}

// The phrases used to be built by string-surgery on the card title, so when the
// cards started saying "I slept for 3h 38m" the sleep pool wrapped that whole
// sentence and printed "I slept I slept for 3h 38m".
it('builds the sleep headline from the duration, not the card title', function () {
    $sleep = Sleep::factory()->create([
        'occurred_at' => '2026-08-29 11:51:49',
        'duration' => 13097,
    ]);

    expect(ogTitle($sleep))
        ->toContain('3h 38m')
        ->not->toContain('I slept for');
});

it('builds the food headline from the day total, not the card title', function () {
    $calorie = Calorie::factory()->create([
        'occurred_at' => '2026-08-29 12:00:00',
        'calories' => 2140,
    ]);

    expect(ogTitle($calorie))
        ->toContain('2,140')
        ->not->toContain('calories');
});

it('says what was watched or read rather than naming it alone', function () {
    $film = Media::factory()->create([
        'type' => 'film',
        'title' => 'Karate Kid',
        'occurred_at' => '2026-08-29 20:00:00',
        'meta' => ['year' => 2010],
    ]);

    $book = Media::factory()->create([
        'type' => 'book',
        'title' => 'Piranesi',
        'occurred_at' => '2026-08-28 20:00:00',
        'meta' => ['author' => 'Susanna Clarke'],
    ]);

    expect(ogTitle($film))->toBe('I watched Karate Kid')
        ->and(ogTitle($book))->toBe('I read Piranesi');
});

it('names the show and the place in it, so a binge is not four identical cards', function () {
    $series = Series::factory()->create(['slug' => 'severance', 'title' => 'Severance']);

    $episode = Media::factory()->create([
        'series_id' => $series->id,
        'type' => 'episode',
        'title' => 'Good News About Hell',
        'occurred_at' => '2026-08-29 21:00:00',
        'meta' => ['season' => 1, 'episode' => 2, 'show_title' => 'Severance'],
    ]);

    expect(ogTitle($episode))->toBe('I watched season 1 episode 2 of Severance');
});
