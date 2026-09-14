<?php

use App\Actions\Og\BuildEntryOgData;
use App\Models\Book;
use App\Models\Film;
use App\Models\Food;
use App\Models\Sleep;
use App\Models\TimelineEntry;
use App\Models\TvEpisode;
use App\Models\TvShow;

/** The headline the OG card would print for the entry behind this model. */
function ogTitle(object $model): string
{
    $entry = TimelineEntry::query()
        ->where('dataset', $model->getMorphClass())
        ->where('entry_id', $model->id)
        ->with('entry')
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
    $food = Food::factory()->create([
        'occurred_at' => '2026-08-29 12:00:00',
        'calories' => 2140,
    ]);

    expect(ogTitle($food))
        ->toContain('2,140')
        ->not->toContain('calories');
});

it('says what was watched or read rather than naming it alone', function () {
    $film = Film::factory()->create([
        'title' => 'Karate Kid',
        'occurred_at' => '2026-08-29 20:00:00',
        'meta' => ['year' => 2010],
    ]);

    $book = Book::factory()->create([
        'title' => 'Piranesi',
        'occurred_at' => '2026-08-28 20:00:00',
        'meta' => ['author' => 'Susanna Clarke'],
    ]);

    expect(ogTitle($film))->toBe('I watched Karate Kid')
        ->and(ogTitle($book))->toBe('I read Piranesi');
});

it('names the show and the place in it, so a binge is not four identical cards', function () {
    $tvShow = TvShow::factory()->create(['slug' => 'severance', 'title' => 'Severance']);

    $episode = TvEpisode::factory()->create([
        'tv_show_id' => $tvShow->id,
        'title' => 'Good News About Hell',
        'occurred_at' => '2026-08-29 21:00:00',
        'meta' => ['season' => 1, 'episode' => 2, 'show_title' => 'Severance'],
    ]);

    expect(ogTitle($episode))->toBe('I watched season 1 episode 2 of Severance');
});

it('says an episode of the show when it has no place in the run', function () {
    $episode = TvEpisode::factory()->create([
        'title' => 'Netherlands (Race)',
        'tv_show_id' => null,
        'occurred_at' => '2026-08-23 20:00:00',
        'meta' => ['show_title' => 'Formula 1'],
    ]);

    expect(ogTitle($episode))->toBe('I watched an episode of Formula 1');
});
