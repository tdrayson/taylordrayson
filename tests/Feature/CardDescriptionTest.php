<?php

use App\Models\Activity;
use App\Models\Appearance;
use App\Models\Article;
use App\Models\Book;
use App\Models\Concerns\Timelineable;
use App\Models\Event;
use App\Models\Film;
use App\Models\Note;
use App\Models\Place;
use App\Models\Project;
use App\Models\ThisWeekWith;
use App\Models\TvEpisode;
use App\Presenters\CardPresenter;

/** The card's standalone line, the one EntryDescription publishes. */
function cardDescription(Timelineable $model): string
{
    return CardPresenter::card($model)->description($model);
}

it('names the film it describes, with the rating as 8/10', function (?int $rating, ?string $overview, string $expected) {
    $film = Film::factory()->make([
        'title' => 'Fall 2: Deadpoint',
        'rating' => $rating,
        'overview' => $overview,
        'meta' => ['year' => 2026],
    ]);

    expect(cardDescription($film))->toBe($expected);
})->with([
    'rated' => [8, null, 'I watched Fall 2: Deadpoint and rated it 8/10.'],
    'unrated' => [null, null, 'I watched Fall 2: Deadpoint.'],
    'overview wins' => [8, 'Two climbers become trapped.', 'Two climbers become trapped.'],
]);

it('names the book and its author', function (?string $author, ?int $rating, ?string $overview, string $expected) {
    $book = Book::factory()->make([
        'title' => 'Project Hail Mary',
        'rating' => $rating,
        'overview' => $overview,
        'meta' => ['author' => $author],
    ]);

    expect(cardDescription($book))->toBe($expected);
})->with([
    'rated, with author' => ['Andy Weir', 9, null, 'I read Project Hail Mary by Andy Weir and rated it 9/10.'],
    'unrated, no author' => [null, null, null, 'I read Project Hail Mary.'],
    'overview wins' => ['Andy Weir', 9, 'Ryland Grace is the sole survivor.', 'Ryland Grace is the sole survivor.'],
]);

it('names the episode by its show and place in the run', function (array $meta, ?int $rating, ?string $overview, string $expected) {
    // tv_show_id null: the tvShow relation would otherwise win over meta.show_title.
    $episode = TvEpisode::factory()->make([
        'title' => 'Pilot',
        'tv_show_id' => null,
        'rating' => $rating,
        'overview' => $overview,
        'meta' => $meta,
    ]);

    expect(cardDescription($episode))->toBe($expected);
})->with([
    'show and place, rated' => [['show_title' => 'Ted Lasso', 'season' => 4, 'episode' => 6], 9, null, 'I watched season 4 episode 6 of Ted Lasso and rated it 9/10.'],
    'show only' => [['show_title' => 'Formula 1'], null, null, 'I watched an episode of Formula 1.'],
    'no show' => [['season' => 1, 'episode' => 3], null, null, 'I watched Pilot.'],
    'overview wins' => [['show_title' => 'Ted Lasso'], 9, "It's New Year's Eve!", "It's New Year's Eve!"],
]);

it('publishes what I wrote on Strava, else the session sentence', function () {
    $written = Activity::factory()->make(['description' => 'Watched the eclipse while playing']);
    $bare = Activity::factory()->make(['type' => 'walk', 'description' => null, 'distance' => 1287, 'duration' => 1080, 'calories' => 78, 'meta' => []]);

    expect(cardDescription($written))->toBe('Watched the eclipse while playing')
        ->and(cardDescription($bare))->toBe(CardPresenter::for($bare)->subtitle)
        ->and(cardDescription($bare))->toStartWith('I walked');
});

it('speaks at a talk and appears on everything else', function (string $type, string $expected) {
    $appearance = Appearance::factory()->create(['type' => $type, 'show_name' => 'Laracon EU', 'description' => null]);

    expect(CardPresenter::for($appearance)->subtitle)->toBe($expected)
        ->and(cardDescription($appearance))->toBe($expected);
})->with([
    'talk' => ['talk', 'I spoke at Laracon EU.'],
    'workshop' => ['workshop', 'I spoke at Laracon EU.'],
    'podcast' => ['podcast', 'I appeared on Laracon EU.'],
    'interview' => ['interview', 'I appeared on Laracon EU.'],
    'livestream' => ['livestream', 'I appeared on Laracon EU.'],
    'unknown kind' => ['panel', 'I appeared on Laracon EU.'],
]);

it('lets an appearance description win over the sentence', function () {
    $appearance = Appearance::factory()->make(['type' => 'podcast', 'show_name' => 'WP Builds', 'description' => 'We talked about blocks.']);

    expect(cardDescription($appearance))->toBe('We talked about blocks.');
});

it('names the event and where it was', function (?string $venue, ?string $city, ?string $note, string $name, string $expected) {
    $event = Event::factory()->make(['name' => $name, 'venue_name' => $venue, 'city' => $city, 'description' => $note]);

    expect(cardDescription($event))->toBe($expected);
})->with([
    'venue and town' => ['Gielgud Theatre', 'London', null, 'Oliver!', 'I went to Oliver! at Gielgud Theatre, London.'],
    'venue only' => ['Gielgud Theatre', null, null, 'Oliver!', 'I went to Oliver! at Gielgud Theatre.'],
    'town only' => [null, 'London', null, 'Hamilton', 'I went to Hamilton in London.'],
    'name already ends the sentence' => [null, null, null, 'Cirque Berserk!', 'I went to Cirque Berserk!'],
    'note wins' => ['Gielgud Theatre', 'London', 'Met Simon Lipkin as Fagin at stage door', 'Oliver!', 'Met Simon Lipkin as Fagin at stage door'],
]);

it('hangs the place off a check-in note without editing it', function (?string $note, ?string $city, string $expected) {
    $place = Place::factory()->make(['venue_name' => 'Costa', 'city' => $city, 'type' => 'Gym and Studio', 'description' => $note]);

    expect(cardDescription($place))->toBe($expected)
        ->and(cardDescription($place))->not->toContain('Gym and Studio');
})->with([
    'plain note' => ['Watching One Night Only with Gordon', 'Crawley', 'Watching One Night Only with Gordon at Costa, Crawley.'],
    'note ends a sentence' => ['Great flat white.', 'Croydon', 'Great flat white. At Costa, Croydon.'],
    'note ends on an emoji' => ['Marty was waiting for me to get back 🐶', 'Croydon', 'Marty was waiting for me to get back 🐶 At Costa, Croydon.'],
    'note ends on a modified emoji' => ['So we settled for Supergirl 🦸🏼‍♀️', 'Crawley', 'So we settled for Supergirl 🦸🏼‍♀️ At Costa, Crawley.'],
    'note, no town' => ['Great coffee', null, 'Great coffee at Costa.'],
    'no note' => [null, 'Bracknell', 'I checked in at Costa in Bracknell.'],
    'no note, no town' => [null, null, 'I checked in at Costa.'],
]);

it('describes the written entry types from their own words', function (Closure $make, string $expected) {
    expect(cardDescription($make()))->toBe($expected);
})->with([
    'this week with topic' => [fn () => ThisWeekWith::factory()->make(['topic' => 'Blocks, bindings']), 'Blocks, bindings'],
    'this week with, no topic' => [fn () => ThisWeekWith::factory()->make(['topic' => null]), ''],
    'article excerpt' => [fn () => Article::factory()->make(['excerpt' => 'The hand-written summary.', 'content' => [['_type' => 'block', 'children' => [['text' => 'The opening prose.']]]]]), 'The hand-written summary.'],
    'article body' => [fn () => Article::factory()->make(['excerpt' => null, 'content' => [['_type' => 'block', 'children' => [['text' => 'The opening prose.']]]]]), 'The opening prose.'],
    'note body' => [fn () => Note::factory()->make(['content' => [['_type' => 'block', 'children' => [['text' => 'A short thought.']]]]]), 'A short thought.'],
    'project description' => [fn () => Project::factory()->make(['title' => 'Glaze', 'description' => 'A macOS app for writing.']), 'A macOS app for writing.'],
    'project, no description' => [fn () => Project::factory()->make(['title' => 'Glaze', 'description' => null]), 'Glaze, a project of mine.'],
]);
