<?php

use App\Actions\Syndicated\PullStravaResponses;
use App\Enums\Source;
use App\Enums\WebmentionKind;
use App\Models\Activity;
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
