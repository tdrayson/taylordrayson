<?php

use App\Actions\Syndicated\PullStravaResponses;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
use App\Models\SyndicatedResponse;
use App\Support\PortableText;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(fn () => config(['services.strava.refresh_token' => 'test-token']));

function stravaActivity(): Activity
{
    return Activity::factory()->create(['source' => Source::Strava->value, 'source_id' => '778']);
}

function fakeStravaResponses(array $kudos, array $comments): void
{
    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/kudos' => MockResponse::make($kudos),
        '/api/v3/activities/778/comments' => MockResponse::make($comments),
    ]);
}

it('stores a kudo as a like, named the way Strava names the person', function () {
    $activity = stravaActivity();
    fakeStravaResponses([['firstname' => 'Justin', 'lastname' => 'M.']], []);

    app(PullStravaResponses::class)($activity);

    $response = $activity->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Like)
        ->and($response->author_name)->toBe('Justin M.')
        ->and($response->source)->toBe(Source::Strava->value);
});

it('stores a comment with its words and its own id', function () {
    $activity = stravaActivity();
    fakeStravaResponses([], [[
        'id' => 2257139458,
        'text' => 'Do they not do padel in here',
        'created_at' => '2025-08-27T09:44:33Z',
        'athlete' => ['firstname' => 'Clare', 'lastname' => 'A.'],
    ]]);

    app(PullStravaResponses::class)($activity);

    $response = $activity->syndicatedResponses()->sole();

    expect($response->kind)->toBe(WebmentionKind::Reply)
        ->and($response->source_id)->toBe('2257139458')
        ->and(PortableText::plainText($response->body))->toBe('Do they not do padel in here')
        ->and($response->occurred_at->toDateString())->toBe('2025-08-27');
});

// The activity is the only address a Strava response has: a comment there is
// not a page of its own.
it('links a response to the activity it was left on', function () {
    $activity = stravaActivity();
    fakeStravaResponses([['firstname' => 'Justin', 'lastname' => 'M.']], []);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->sole()->url)->toBe('https://www.strava.com/activities/778');
});

it('does nothing at all for an activity that did not come from Strava', function () {
    $activity = Activity::factory()->create(['source' => Source::Setgraph->value, 'source_id' => '9']);
    Saloon::fake(['*' => MockResponse::make([], 500)]);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->count())->toBe(0);
    Saloon::assertNothingSent();
});

// Null is a failed request, not an empty list. This is the one branch standing
// between an API blip and wiping every response already stored on an activity.
it('leaves stored responses untouched when the kudos request fails', function () {
    $activity = stravaActivity();
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value, 'kind' => WebmentionKind::Like, 'author_name' => 'Existing',
    ]);

    Saloon::fake([
        '/oauth/token*' => MockResponse::make(['access_token' => 'token', 'expires_in' => 3600]),
        '/api/v3/activities/778/kudos' => MockResponse::make([], 500),
        '/api/v3/activities/778/comments' => MockResponse::make([]),
    ]);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->sole()->author_name)->toBe('Existing');
});

// Strava sends at most one page and says nothing about there being more, so a
// page filled to the ceiling cannot be read as the whole list. Deleting on its
// absences would destroy real comments and re-fetch them on every run.
it('keeps stored comments when Strava fills a whole page', function () {
    $activity = stravaActivity();

    $page = collect(range(1, 200))->map(fn (int $n): array => [
        'id' => $n,
        'text' => "Comment {$n}",
        'created_at' => '2025-08-27T09:44:33Z',
        'athlete' => ['firstname' => 'Clare', 'lastname' => 'A.'],
    ])->all();

    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value,
        'source_id' => 'held-over-from-an-earlier-page',
        'kind' => WebmentionKind::Reply,
    ]);

    fakeStravaResponses([], $page);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->where('kind', WebmentionKind::Reply)->count())->toBe(201);
});

it('keeps stored kudos when Strava fills a whole page', function () {
    $activity = stravaActivity();

    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value,
        'kind' => WebmentionKind::Like,
        'author_name' => 'Held over',
    ]);

    fakeStravaResponses(collect(range(1, 200))->map(fn (int $n): array => [
        'firstname' => 'Athlete', 'lastname' => (string) $n,
    ])->all(), []);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->where('author_name', 'Held over')->count())->toBe(1);
});

// A short page is the whole list, so the usual reconciliation still applies.
it('still removes a comment that has gone when the page is not full', function () {
    $activity = stravaActivity();

    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value,
        'source_id' => 'withdrawn',
        'kind' => WebmentionKind::Reply,
    ]);

    fakeStravaResponses([], []);

    app(PullStravaResponses::class)($activity);

    expect($activity->syndicatedResponses()->count())->toBe(0);
});

// A comments endpoint that was never called returns an empty list that proves
// nothing, unlike one that was asked and answered. Reading it as "every
// comment withdrawn" would make the cheap path delete what the expensive path
// preserves. Only reachable here: the command always fetches when replies are
// held, so this guard protects any other caller of the action.
it('keeps stored replies when asked not to fetch comments', function () {
    $activity = stravaActivity();
    SyndicatedResponse::factory()->for($activity, 'target')->create([
        'source' => Source::Strava->value,
        'source_id' => 'kept',
        'kind' => WebmentionKind::Reply,
    ]);

    fakeStravaResponses([], []);

    app(PullStravaResponses::class)($activity, withComments: false);

    Saloon::assertNotSent(fn ($request): bool => str_contains($request->resolveEndpoint(), '/comments'));
    expect($activity->syndicatedResponses()->where('kind', WebmentionKind::Reply)->count())->toBe(1);
});
