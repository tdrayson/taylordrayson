<?php

use App\Enums\MediaType;
use App\Models\Activity;
use App\Models\Airport;
use App\Models\Article;
use App\Models\Checkin;
use App\Models\Flight;
use App\Models\Fuel;
use App\Models\Media;
use App\Models\Note;
use App\Models\Page;
use App\Models\Sleep;
use App\Models\Tag;

use function Pest\Laravel\get;

/**
 * The meta description used to open on the very words of the title above it, so
 * the assertion throughout is that it opens differently and reads as a sentence
 * somebody would say. Naming the same thing again is fine; starting the same
 * way is not. Where the source wrote its own words, those words are the
 * description and nothing generated replaces them.
 */
it('describes a sleep entry with its duration, date and window', function () {
    $sleep = Sleep::factory()->create([
        'occurred_at' => '2026-08-24 00:00:00',
        'bedtime' => '2026-08-23 23:30:00',
        'wake_time' => '2026-08-24 08:51:00',
        'duration' => 33660,
        'score' => 80,
    ]);

    get('/'.$sleep->occurred_at->format('Y/m/d').'/'.$sleep->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.title', 'I slept for 9h 21m - 24 Aug 2026')
            ->where('og.description', 'I went to bed at 11:30pm and woke at 8:51am. My sleep score was 80.')
        );
});

it('dates a log entry title so repeated names stay distinct', function () {
    $first = Activity::factory()->create(['name' => 'Walk', 'type' => 'walk', 'occurred_at' => '2026-03-15 07:30:00']);
    $second = Activity::factory()->create(['name' => 'Walk', 'type' => 'walk', 'occurred_at' => '2026-04-02 07:30:00']);

    $titleOf = function (Activity $activity): string {
        $response = get('/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug())->assertOk();

        return $response->viewData('page')['props']['og']['title'];
    };

    expect($titleOf($first))->toBe('Walk - 15 Mar 2026')
        ->and($titleOf($second))->toBe('Walk - 2 Apr 2026');
});

it('names the show in front of an episode title', function () {
    $episode = Media::factory()->create([
        'type' => MediaType::TvEpisode,
        'title' => 'Netherlands (Race)',
        'occurred_at' => '2026-08-23 20:00:00',
        'meta' => ['show_title' => 'Formula 1', 'season' => 2026, 'episode' => 69],
    ]);

    get('/'.$episode->occurred_at->format('Y/m/d').'/'.$episode->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.title', 'Formula 1: Netherlands (Race) - 23 Aug 2026')
            ->where('og.description', fn (string $value): bool => str_starts_with($value, 'I watched season 2026, episode 69 of Formula 1.'))
        );
});

it('describes a check-in with its venue, category and town as a sentence', function () {
    $checkin = Checkin::factory()->create([
        'venue_name' => 'Starbucks',
        'category' => 'Coffee Shop',
        'city' => 'Bracknell',
        'description' => null,
        'occurred_at' => '2026-08-24 09:00:00',
    ]);

    get('/'.$checkin->occurred_at->format('Y/m/d').'/'.$checkin->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'I checked in at Starbucks, a Coffee Shop in Bracknell.')
        );
});

it('prefers an article excerpt over its opening prose', function () {
    $article = Article::factory()->create([
        'title' => 'A Title',
        'excerpt' => 'The hand-written summary.',
        'published' => true,
        'occurred_at' => '2026-08-01 10:00:00',
        'content' => [['_type' => 'block', 'children' => [['text' => 'The opening prose instead.']]]],
    ]);

    get('/'.$article->occurred_at->format('Y/m/d').'/'.$article->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.title', 'A Title')
            ->where('og.description', 'The hand-written summary.')
        );
});

it('falls back to a note body for its own description', function () {
    $note = Note::factory()->create([
        'occurred_at' => '2026-08-15 12:00:00',
        'content' => [['_type' => 'block', 'children' => [['text' => 'A short thought worth indexing.']]]],
    ]);

    get('/'.$note->occurred_at->format('Y/m/d').'/'.$note->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'A short thought worth indexing.')
        );
});

it('counts the archive it describes', function () {
    Activity::factory()->count(3)->create(['type' => 'run']);

    get('/activities')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.title', 'All Activities')
            ->where('og.description', "All 3 activities I've logged, newest first.")
        );
});

it('counts a taxonomy archive it describes', function () {
    Checkin::factory()->count(2)->create(['category' => 'Coffee Shop']);

    get('/places/coffee-shop')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'Coffee Shops: all 2, newest first.')
        );
});

it('counts the entries behind a tag', function () {
    $tag = Tag::create(['name' => 'coffee', 'slug' => 'coffee']);
    $note = Note::factory()->create(['content' => [['_type' => 'block', 'children' => [['text' => 'hi']]]]]);
    $note->tags()->attach($tag);

    get('/tags/coffee')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'Everything tagged coffee: 1 entry from across every type I track, newest first.')
        );
});

it('uses page prose when a page has no excerpt', function () {
    Page::factory()->create([
        'title' => 'Colophon',
        'slug' => 'colophon',
        'excerpt' => null,
        'published' => true,
        'content' => [['_type' => 'block', 'children' => [['text' => 'How this site is built.']]]],
    ]);

    get('/colophon')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'How this site is built.')
        );
});

it('publishes what I wrote on Strava rather than the numbers it could generate', function () {
    $activity = Activity::factory()->create([
        'name' => 'Evening Tennis',
        'type' => 'workout',
        'description' => 'Watched the solar eclipse while playing',
        'occurred_at' => '2026-08-12 18:00:00',
    ]);

    get('/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'Watched the solar eclipse while playing')
        );
});

it('describes an activity with no words of its own from its numbers', function () {
    $activity = Activity::factory()->create([
        'name' => 'Evening Walk',
        'type' => 'walk',
        'description' => null,
        'distance' => 1287,
        'duration' => 1080,
        'calories' => 78,
        'meta' => [],
        'occurred_at' => '2026-08-31 18:00:00',
    ]);

    get('/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', fn (string $value): bool => str_starts_with($value, 'I walked')
                && ! str_contains($value, 'Evening Walk'))
        );
});

// The note is my own sentence, so the place hangs off the end of it rather
// than being folded into a rewrite.
it('keeps a check-in note whole and hangs the place off the end', function () {
    $checkin = Checkin::factory()->create([
        'venue_name' => 'Cineworld',
        'city' => 'Crawley',
        'description' => 'Watching One Night Only with Gordon',
        'occurred_at' => '2026-09-02 19:00:00',
    ]);

    get('/'.$checkin->occurred_at->format('Y/m/d').'/'.$checkin->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'Watching One Night Only with Gordon at Cineworld, Crawley.')
        );
});

it('starts a new sentence when the note it follows already ended one', function () {
    $checkin = Checkin::factory()->create([
        'venue_name' => 'Costa',
        'city' => 'Croydon',
        'description' => 'Great flat white.',
        'occurred_at' => '2026-09-01 09:00:00',
    ]);

    get('/'.$checkin->occurred_at->format('Y/m/d').'/'.$checkin->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'Great flat white. At Costa, Croydon.')
        );
});

// " | Taylor Drayson" is appended in the browser, so a title measured without
// it overflows the search result by exactly that much.
it('leaves room for the site name when it cuts a long title', function () {
    $activity = Activity::factory()->create([
        'name' => 'A very long activity name that would run past where Google cuts it off',
        'type' => 'walk',
        'occurred_at' => '2026-08-31 07:00:00',
    ]);

    $response = get('/'.$activity->occurred_at->format('Y/m/d').'/'.$activity->slug())->assertOk();
    $title = $response->viewData('page')['props']['og']['title'];

    expect(mb_strlen($title.' | Taylor Drayson'))->toBeLessThanOrEqual(60);
});

// The title only has room for the codes, so naming the airports is the whole
// job of this line. Their `city` cannot do it: Gatwick, Heathrow and Stansted
// are all "London", and Kraków's is "Balice".
it('names the airports the title could only code', function () {
    Airport::factory()->create(['iata_code' => 'KRK', 'name' => 'Kraków John Paul II International Airport', 'city' => 'Balice']);
    Airport::factory()->create(['iata_code' => 'LGW', 'name' => 'London Gatwick Airport', 'city' => 'London']);

    $flight = Flight::factory()->create([
        'origin_iata' => 'KRK',
        'destination_iata' => 'LGW',
        'occurred_at' => '2026-06-08 22:15:00',
    ]);

    get('/'.$flight->occurred_at->format('Y/m/d').'/'.$flight->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', fn (string $value): bool => str_starts_with(
                $value,
                'I flew from Kraków John Paul II International Airport to London Gatwick Airport',
            ))
        );
});

// "A BP garage" is what anyone would call it. "Beddington Lane Service Station"
// is a name only its own paperwork uses.
it('names a fill-up by its brand rather than its forecourt', function () {
    $fuel = Fuel::factory()->create([
        'brand' => 'BP',
        'station_name' => 'Beddington Lane Service Station',
        'city' => 'Croydon',
        'litres' => 31.279,
        'cost' => 50.64,
        'price_per_litre' => 1.619,
        'occurred_at' => '2026-08-28 17:00:00',
    ]);

    get('/'.$fuel->occurred_at->format('Y/m/d').'/'.$fuel->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'I filled my car with 31.28 litres at a BP garage in Croydon. Fuel was 161.9p a litre, so it cost £50.64.')
        );
});

// 81 of the older rows carry no forecourt at all, and the sentence still has to
// stand up without one.
it('still reads as a sentence when a fill-up has no garage on it', function () {
    $fuel = Fuel::factory()->create([
        'brand' => null,
        'station_name' => null,
        'city' => null,
        'litres' => 40.0,
        'cost' => 60.0,
        'price_per_litre' => 1.5,
        'occurred_at' => '2026-08-27 17:00:00',
    ]);

    get('/'.$fuel->occurred_at->format('Y/m/d').'/'.$fuel->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'I filled my car with 40.00 litres. Fuel was 150.0p a litre, so it cost £60.00.')
        );
});

it('plays a sport rather than covering it', function () {
    $padel = Activity::factory()->create([
        'name' => 'Afternoon Padel',
        'type' => 'padel',
        'distance' => 3540,
        'duration' => 6360,
        'calories' => null,
        'meta' => [],
        'occurred_at' => '2026-08-30 15:00:00',
    ]);

    get('/'.$padel->occurred_at->format('Y/m/d').'/'.$padel->slug())
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('og.description', 'I played padel for 1h 46m, covering 2.2 mi.')
        );
});
